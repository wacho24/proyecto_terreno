<?php
// pages/ventas_sources.php
require_once __DIR__ . '/_guard.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); } // ⬅️ NUEVO
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['id']) : '';
if ($id === '') { echo json_encode(['ok'=>false,'error'=>'id vacío']); exit; }

require_once __DIR__ . '/../config/firebase_init.php';

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';
$empId = isset($_SESSION['user_id']) ? preg_replace('/[^A-Za-z0-9_\-]/','',$_SESSION['user_id']) : ''; // ⬅️ NUEVO

$clientes = [];
$lotes    = [];
$fotoDesarrollo = '';

try {
  // === 1) Foto del desarrollo ===
  // a) Primer intento: bajo el empresario logueado
  if ($empId !== '') {
    $snapFoto = $database
      ->getReference("$ROOT_PREFIX/DesarrollosEmpresarios/$empId/Desarrollos/$id/FotoDesarrollo")
      ->getSnapshot();
    $fotoDesarrollo = (string)($snapFoto->getValue() ?? '');
  }

  // b) Fallback: bajo DesarrollosGenerales/$id
  if ($fotoDesarrollo === '') {
    $snapInfo = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$id")->getSnapshot();
    $tmpInfo  = $snapInfo->getValue() ?: [];
    $fotoDesarrollo =
        $tmpInfo['FotoDesarrollo']
        ?? $tmpInfo['fotoDesarrollo']
        ?? $tmpInfo['Foto']
        ?? $tmpInfo['foto']
        ?? '';
    $desarrolloIdCampo = $tmpInfo['idDesarrollo'] ?? '';
  } else {
    // si ya teníamos la foto, igualmente cargamos info de Generales por clientes/lotes
    $snapInfo = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$id")->getSnapshot();
    $tmpInfo  = $snapInfo->getValue() ?: [];
    $desarrolloIdCampo = $tmpInfo['idDesarrollo'] ?? '';
  }

  // === 2) CLIENTES (varias rutas) ===
  $candidates = [
    "$ROOT_PREFIX/DesarrollosClientes/$id/Clientes",
    "$ROOT_PREFIX/DesarrollosGenerales/$id/Clientes",
  ];
  if (!empty($desarrolloIdCampo)) {
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

  // === 3) LOTES DISPONIBLES ===
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
      'id'              => $loteId,
      'manzanaId'       => $manzanaId,
      'label'           => $label,
      'precio'          => $pventa,
      'estado'          => $estado,
      'FotoDesarrollo'  => $fotoDesarrollo, // ← la incluimos con cada lote por comodidad del front
    ];
  }

  echo json_encode([
    'ok'             => true,
    'clientes'       => $clientes,
    'lotes'          => $lotes,
    'fotoDesarrollo' => $fotoDesarrollo,   // ← y también a nivel raíz
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch(Throwable $e){
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
