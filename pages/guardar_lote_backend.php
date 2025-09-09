<?php
// pages/guardar_lote_backend.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/firebase_init.php';

header('Content-Type: application/json; charset=UTF-8');

function jres(array $d, int $c=200){ http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function sid(string $v): string { return preg_replace('/[^A-Za-z0-9_\-]/','', $v); }

/**
 * Parser robusto de montos:
 * - Acepta 10000, 10,000, 10.000, 10000,50, 10,000.50, 10.000,50, etc.
 * - Toma el último separador (, o .) como decimal y elimina el resto (miles).
 */
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

  $s = preg_replace('/[^0-9\.\-]/', '', $s);
  return is_numeric($s) ? (float)$s : 0.0;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jres(['status'=>'error','message'=>'Método no permitido'], 405);
  }

  $email = (string)($_SESSION['email'] ?? '');
  $uid   = (string)($_SESSION['idDesarrollo'] ?? '');
  if ($email==='' || $uid==='') jres(['status'=>'error','message'=>'NOT_LOGGED_IN'], 401);

  $base = 'projects/proj_8HNCM2DFob/data';

  $desarrolloRecordId = sid((string)($_POST['desarrolloRecordId'] ?? $uid));
  $manzanaId          = sid((string)($_POST['manzanaId'] ?? ''));
  $descripcion        = trim((string)($_POST['descripcion'] ?? ''));  // Denominación del lote
  $costo              = fnum($_POST['costo']  ?? 0);
  $precio             = fnum($_POST['precio'] ?? 0);
  $estadoUi           = trim((string)($_POST['estado'] ?? 'DISPONIBLE'));
  $nota               = trim((string)($_POST['nota']   ?? ''));

  $mf   = fnum($_POST['mf'] ?? 0);
  $md   = fnum($_POST['md'] ?? 0);
  $mi   = fnum($_POST['mi'] ?? 0);
  $mp   = fnum($_POST['mp'] ?? 0);
  $area = isset($_POST['area']) ? fnum($_POST['area']) : ($md + $mi) * ($mf + $mp);

  if ($desarrolloRecordId==='' || $manzanaId==='' || $descripcion==='' || $precio<=0 || $mf<=0 || $md<=0 || $mi<=0 || $mp<=0) {
    jres(['status'=>'error','message'=>'Parámetros obligatorios faltantes'], 422);
  }

  // Info padre (para réplica) y nombre de la manzana
  $info         = $database->getReference("$base/DesarrollosGenerales/$desarrolloRecordId")->getValue() ?: [];
  $idDesarrollo = (string)($info['idDesarrollo'] ?? '');
  $replica      = ($idDesarrollo !== '');

  $nombreManzana = (string)($database
      ->getReference("$base/DesarrollosGenerales/$desarrolloRecordId/Manzanas/$manzanaId/NombreManzana")
      ->getValue() ?? '');

  // Crear clave para que idLote sea igual al Record id
  $pathLotes = "$base/DesarrollosGenerales/$desarrolloRecordId/Lotes";
  $newRef    = $database->getReference($pathLotes)->push();
  $loteId    = $newRef->getKey();

  $estatus      = strtoupper($estadoUi ?: 'DISPONIBLE');
  $precioMetro  = ($area > 0) ? round($precio / $area, 2) : 0.0;

  // Payload final (incluye compatibilidad con nombres anteriores)
  $data = [
    // Identificadores
    'idLote'        => $loteId,
    'idManzana'     => $manzanaId,
    'ManzanaId'     => $manzanaId,        // compatibilidad

    // Nombres
    'NombreLote'    => $descripcion,
    'Descripcion'   => $descripcion,      // compatibilidad
    'NombreManzana' => $nombreManzana,

    // Económicos
    'Costo'         => $costo,
    'Precio'        => $precio,
    'PrecioVenta'   => $precio,           // compatibilidad
    'PrecioMetro'   => $precioMetro,

    // Estado
    'Estatus'       => $estatus,

    // Medidas (nueva nomenclatura)
    'MedidaFrente'    => $mf,
    'MedidaDerecho'   => $md,
    'MedidaIzquierdo' => $mi,
    'MedidaFondo'     => $mp,

    // Otros
    'Area'         => $area,
    'Nota'         => $nota,
    'createdAt'    => time(),
    'createdBy'    => $email,
  ];

  // Guardar
  $newRef->set($data);

  // Réplica opcional con mismo id
  if ($replica) {
    $database->getReference("$base/Empresarios/$idDesarrollo/Lotes/$loteId")->set($data);
  }

  jres([
    'status'              => 'ok',
    'loteId'              => $loteId,
    'desarrolloRecordId'  => $desarrolloRecordId,
    'replicadoEmpresario' => $replica,
    'idDesarrollo'        => $idDesarrollo
  ]);

} catch (Throwable $e) {
  jres(['status'=>'error','message'=>$e->getMessage()], 500);
}
