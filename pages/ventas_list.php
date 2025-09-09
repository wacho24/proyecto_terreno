<?php
// pages/ventas_list.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['id']) : '';
if ($id === '') { echo json_encode(['ok'=>false,'error'=>'id vacío']); exit; }

require_once __DIR__ . '/../config/firebase_init.php';
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

try {
  $path = "$ROOT_PREFIX/VentasGenerales";
  $snap = $database->getReference($path)->getSnapshot();
  $rows = $snap->getValue() ?: [];

  $items = [];
  foreach ($rows as $rid => $row) {
    // Si existe idDesarrollo en la fila, filtramos estrictamente
    if (isset($row['idDesarrollo']) && $row['idDesarrollo'] !== $id) continue;

    $items[] = [
      'id'        => $rid,
      'cliente'   => (string)($row['NombreCliente']   ?? ''),
      'lote'      => (string)($row['NombreLote']      ?? ''),
      'fecha'     => (string)($row['FechaVenta']      ?? ''),
      'tipo'      => (string)($row['TipoVenta']       ?? ''),
      'total'     => (float)  ($row['PrecioLote']     ?? 0),
      'estatus'   => (string)($row['Estatus']         ?? ''),
      'contrato'  => (string)($row['NumeroContrato']  ?? ''),
    ];
  }

  // Orden opcional (más nuevas arriba si FechaVenta es DD/MM/YYYY)
  usort($items, function($a,$b){
    $pa = DateTime::createFromFormat('d/m/Y', $a['fecha']) ?: new DateTime('1970-01-01');
    $pb = DateTime::createFromFormat('d/m/Y', $b['fecha']) ?: new DateTime('1970-01-01');
    return $pb <=> $pa;
  });

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
