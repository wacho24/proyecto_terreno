<?php
// pages/ventas_sources.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['id']) : '';
if ($id === '') { echo json_encode(['ok'=>false,'error'=>'id vacío']); exit; }

require_once __DIR__ . '/../config/firebase_init.php';

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

$clientes = [];
$lotes    = [];

try {
  // CLIENTES (varias rutas posibles)
  $snapInfo = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$id")->getSnapshot();
  $tmpInfo  = $snapInfo->getValue() ?: [];
  $desarrolloIdCampo = $tmpInfo['idDesarrollo'] ?? '';

  $candidates = [
    "$ROOT_PREFIX/DesarrollosClientes/$id/Clientes",
    "$ROOT_PREFIX/DesarrollosGenerales/$id/Clientes",
  ];
  if ($desarrolloIdCampo !== '') {
    $candidates[] = "$ROOT_PREFIX/Empresarios/$desarrolloIdCampo/Clientes";
  }

  $raw = [];
  foreach ($candidates as $p) {
    try {
      $s = $database->getReference($p)->getSnapshot();
      $v = $s->getValue();
      if (is_array($v) && count($v)) { $raw = $v; break; }
    } catch(Throwable $e){}
  }
  if (is_array($raw)) {
    foreach ($raw as $rid => $row) {
      $clientes[] = [
        'id'       => $rid,
        'nombre'   => $row['Nombre'] ?? '',
        'telefono' => $row['Telefono'] ?? '',
        'email'    => $row['Email'] ?? '',
      ];
    }
  }

  // LOTES DISPONIBLES
  $pathLotes = "$ROOT_PREFIX/DesarrollosGenerales/$id/Lotes";
  $snapLotes = $database->getReference($pathLotes)->getSnapshot();
  $rowsLotes = $snapLotes->getValue() ?: [];

  foreach ($rowsLotes as $loteId => $l) {
    $estado = strtoupper((string)($l['Estatus'] ?? $l['Estado'] ?? 'DISPONIBLE'));
    if ($estado !== 'DISPONIBLE') continue;

    $manzanaId = (string)($l['idManzana'] ?? $l['ManzanaId'] ?? '');
    $desc      = (string)($l['NombreLote'] ?? $l['Descripcion'] ?? $loteId);
    $manzNom   = (string)($l['NombreManzana'] ?? $l['ManzanaNombre'] ?? $manzanaId);
    $pventa    = (float)($l['Precio'] ?? $l['PrecioVenta'] ?? 0);

    $label = trim($desc) . ' — ' . trim($manzNom);
    $lotes[] = [
      'id'        => $loteId,
      'manzanaId' => $manzanaId,
      'label'     => $label,
      'precio'    => $pventa,
      'estado'    => $estado
    ];
  }

  echo json_encode(['ok'=>true, 'clientes'=>$clientes, 'lotes'=>$lotes], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
