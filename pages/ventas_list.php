<?php
// pages/ventas_list.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

$idDesarrollo = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$_GET['id']) : '';
if ($idDesarrollo === '') { echo json_encode(['ok'=>false,'error'=>'id vacío']); exit; }

require_once __DIR__ . '/../config/firebase_init.php';
/** @var \Kreait\Firebase\Database $database */
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

/* ===== helpers ===== */
function normalize_money($v){
  if (is_numeric($v)) return (float)$v;
  $s = preg_replace('/[^\d,\.\-]/', '', (string)$v);
  return $s === '' ? 0.0 : (float)$s;
}

function to_dmY($val){
  $val = trim((string)$val);
  if ($val === '') return '';
  $d = DateTime::createFromFormat('d/m/Y', $val);
  if ($d instanceof DateTime) return $d->format('d/m/Y');
  $d = DateTime::createFromFormat('Y-m-d', $val);
  if ($d instanceof DateTime) return $d->format('d/m/Y');
  $t = strtotime($val);
  if ($t !== false) return date('d/m/Y', $t);
  return $val;
}

try {
  // 1) Cargar desarrollo específico
  $desPath = "$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo";
  $snapDes = $database->getReference($desPath)->getSnapshot();
  if (!$snapDes->exists()) {
    echo json_encode(['ok'=>false,'error'=>'desarrollo no encontrado']); exit;
  }

  // 2) Lotes del desarrollo
  $lotPath = "$desPath/Lotes";
  $snapLot = $database->getReference($lotPath)->getSnapshot();
  $lotes = $snapLot->getValue() ?: [];

  $items = [];

  foreach ($lotes as $idLote => $row) {
    if (!is_array($row)) continue;

    $status = (string)($row['Estatus'] ?? $row['estatus'] ?? '');
    if (mb_strtoupper(trim($status)) === 'DISPONIBLE') continue;

    $cliente    = (string)($row['NombreCliente'] ?? $row['Cliente'] ?? '');
    $loteLbl    = (string)($row['NombreLote'] ?? $row['Lote'] ?? $idLote);
    $precio     = normalize_money($row['Precio'] ?? $row['Costo'] ?? 0);
    $fechaVenta = to_dmY($row['FechaVenta'] ?? '');
    $tipoVenta  = (string)($row['TipoVenta'] ?? '');

    $items[] = [
      'id'         => (string)$idLote,   // mismo que idLote
      'idLote'     => (string)$idLote,
      'idDesarrollo' => (string)$idDesarrollo, // 👈 guardamos también el desarrollo
      'cliente'    => $cliente,
      'lote'       => $loteLbl,
      'precio'     => $precio,
      'fechaVenta' => $fechaVenta,
      'tipoVenta'  => $tipoVenta,
      'status'     => $status,
    ];
  }

  // 3) Ordenar por fecha descendente
  usort($items, function($a,$b){
    $pa = DateTime::createFromFormat('d/m/Y', (string)$a['fechaVenta']) ?: new DateTime('1970-01-01');
    $pb = DateTime::createFromFormat('d/m/Y', (string)$b['fechaVenta']) ?: new DateTime('1970-01-01');
    return $pb <=> $pa;
  });

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
