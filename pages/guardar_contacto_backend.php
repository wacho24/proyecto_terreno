<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/_guard.php';
require_once __DIR__.'/../config/firebase_init.php';
header('Content-Type: application/json; charset=UTF-8');

function jres(array $d,int $c=200){ http_response_code($c); echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function sid(string $v): string { return preg_replace('/[^A-Za-z0-9_\-]/','',$v); }
function s(string $v): string { return trim((string)$v); }

try{
  if($_SERVER['REQUEST_METHOD']!=='POST') jres(['status'=>'error','message'=>'Método no permitido'],405);

  $email = (string)($_SESSION['email'] ?? '');
  $uid   = (string)($_SESSION['idDesarrollo'] ?? '');
  if($email===''||$uid==='') jres(['status'=>'error','message'=>'NOT_LOGGED_IN'],401);

  $base = 'projects/proj_8HNCM2DFob/data';
  $desarrolloRecordId = sid((string)($_POST['desarrolloRecordId'] ?? $uid));

  // Campos del modal
  $nombres   = s($_POST['nombres']   ?? '');
  $apellidos = s($_POST['apellidos'] ?? '');
  $telefono  = s($_POST['telefono']  ?? '');
  $correo    = s($_POST['correo']    ?? '');
  $direccion = s($_POST['direccion'] ?? '');
  $curp      = strtoupper(s($_POST['curp'] ?? ''));
  $rfc       = strtoupper(s($_POST['rfc']  ?? ''));
  $nota      = s($_POST['nota']      ?? '');

  if($nombres==='') jres(['status'=>'error','message'=>'El nombre es obligatorio'],422);

  // Para réplica
  $info = $database->getReference("$base/DesarrollosGenerales/$desarrolloRecordId")->getValue() ?: [];
  $idDesarrollo = (string)($info['idDesarrollo'] ?? '');
  $replica = ($idDesarrollo !== '');

  // Guardar
  $path = "$base/DesarrollosGenerales/$desarrolloRecordId/Contactos";
  $ref  = $database->getReference($path)->push();
  $contactoId = $ref->getKey();

  $data = [
    'idContacto' => $contactoId,
    'Nombres'    => $nombres,
    'Apellidos'  => $apellidos,
    'Telefono'   => $telefono,
    'Correo'     => $correo,
    'Direccion'  => $direccion,
    'CURP'       => $curp,
    'RFC'        => $rfc,
    'Nota'       => $nota,
    'createdAt'  => time(),
    'createdBy'  => $email,
  ];
  $ref->set($data);

  if($replica){
    $database->getReference("$base/Empresarios/$idDesarrollo/Contactos/$contactoId")->set($data);
  }

  jres(['status'=>'ok','contactoId'=>$contactoId,'replicadoEmpresario'=>$replica,'idDesarrollo'=>$idDesarrollo]);

}catch(Throwable $e){
  jres(['status'=>'error','message'=>$e->getMessage()],500);
}
