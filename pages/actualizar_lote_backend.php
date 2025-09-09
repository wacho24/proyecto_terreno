<?php
// pages/actualizar_lote_backend.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/firebase_init.php';

header('Content-Type: application/json; charset=UTF-8');

function jres(array $d, int $c=200){ http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function sid(string $v): string { return preg_replace('/[^A-Za-z0-9_\-]/','', $v); }

function fnum($v): float {
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(' ', '', $s);

  $hasComma = strpos($s, ',') !== false;
  $hasDot   = strpos($s, '.') !== false;

  if ($hasComma && $hasDot) {
    $lastComma = strrpos($s, ',');
    $lastDot   = strrpos($s, '.');
    if ($lastComma > $lastDot) {
      $s = str_replace('.', '', $s);
      $s = str_replace(',', '.', $s);
    } else {
      $s = str_replace(',', '', $s);
    }
  } elseif ($hasComma) {
    $last = strrpos($s, ',');
    $dec  = strlen($s) - $last - 1;
    if ($dec === 3) $s = str_replace(',', '', $s);
    else $s = str_replace(',', '.', $s);
  } elseif ($hasDot) {
    $last = strrpos($s, '.');
    $dec  = strlen($s) - $last - 1;
    if ($dec === 3) $s = str_replace('.', '', $s);
  }

  $s = preg_replace('/[^0-9\.\-]/','', $s);
  return is_numeric($s) ? (float)$s : 0.0;
}

try {
  if ($_SERVER['REQUEST_METHOD']!=='POST') {
    jres(['status'=>'error','message'=>'Método no permitido'], 405);
  }

  $email = (string)($_SESSION['email'] ?? '');
  $uid   = (string)($_SESSION['idDesarrollo'] ?? '');
  if ($email==='' || $uid==='') jres(['status'=>'error','message'=>'NOT_LOGGED_IN'], 401);

  $base = 'projects/proj_8HNCM2DFob/data';

  $desarrolloRecordId = sid((string)($_POST['desarrolloRecordId'] ?? $uid));
  $loteId             = sid((string)($_POST['loteId'] ?? ''));
  if ($desarrolloRecordId==='' || $loteId==='') jres(['status'=>'error','message'=>'Faltan parámetros'], 422);

  $manzanaId   = sid((string)($_POST['manzanaId'] ?? ''));
  $descripcion = trim((string)($_POST['descripcion'] ?? ''));
  $costo       = fnum($_POST['costo']  ?? 0);
  $precio      = fnum($_POST['precio'] ?? 0);
  $estadoUi    = trim((string)($_POST['estado'] ?? 'DISPONIBLE'));
  $nota        = trim((string)($_POST['nota']   ?? ''));

  $mf   = fnum($_POST['mf'] ?? 0);
  $md   = fnum($_POST['md'] ?? 0);
  $mi   = fnum($_POST['mi'] ?? 0);
  $mp   = fnum($_POST['mp'] ?? 0);
  $area = isset($_POST['area']) ? fnum($_POST['area']) : ($md + $mi) * ($mf + $mp);

  if ($manzanaId==='' || $descripcion==='' || $precio<=0 || $mf<=0 || $md<=0 || $mi<=0 || $mp<=0) {
    jres(['status'=>'error','message'=>'Parámetros obligatorios faltantes'], 422);
  }

  // Info para réplica y nombre de la manzana
  $info         = $database->getReference("$base/DesarrollosGenerales/$desarrolloRecordId")->getValue() ?: [];
  $idDesarrollo = (string)($info['idDesarrollo'] ?? '');
  $replica      = ($idDesarrollo !== '');

  $nombreManzana = (string)($database
      ->getReference("$base/DesarrollosGenerales/$desarrolloRecordId/Manzanas/$manzanaId/NombreManzana")
      ->getValue() ?? '');

  $estatus      = strtoupper($estadoUi ?: 'DISPONIBLE');
  $precioMetro  = ($area > 0) ? round($precio / $area, 2) : 0.0;

  $updates = [
    // Identificadores (NO modificar idLote)
    'idManzana'     => $manzanaId,
    'ManzanaId'     => $manzanaId,        // compatibilidad

    // Nombres
    'NombreLote'    => $descripcion,
    'Descripcion'   => $descripcion,      // compatibilidad
    'NombreManzana' => $nombreManzana,

    // Económicos
    'Costo'        => $costo,
    'Precio'       => $precio,
    'PrecioVenta'  => $precio,            // compatibilidad
    'PrecioMetro'  => $precioMetro,

    // Estado
    'Estatus'      => $estatus,

    // Medidas
    'MedidaFrente'    => $mf,
    'MedidaDerecho'   => $md,
    'MedidaIzquierdo' => $mi,
    'MedidaFondo'     => $mp,

    // Otros
    'Area'       => $area,
    'Nota'       => $nota,
    'updatedAt'  => time(),
    'updatedBy'  => $email,
  ];

  $refGen = "$base/DesarrollosGenerales/$desarrolloRecordId/Lotes/$loteId";
  $database->getReference($refGen)->update($updates);

  if ($replica) {
    $database->getReference("$base/Empresarios/$idDesarrollo/Lotes/$loteId")->update($updates);
  }

  jres([
    'status'              => 'ok',
    'loteId'              => $loteId,
    'replicadoEmpresario' => $replica,
    'idDesarrollo'        => $idDesarrollo
  ]);

} catch (Throwable $e) {
  jres(['status'=>'error','message'=>$e->getMessage()], 500);
}
