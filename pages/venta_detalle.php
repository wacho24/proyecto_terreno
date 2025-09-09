<?php
// pages/venta_detalle.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $raw = file_get_contents('php://input');
  $in  = $raw ? json_decode($raw, true) : null;
  $idVenta = $in['idVenta'] ?? ($_GET['idVenta'] ?? '');
  $idVenta = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$idVenta);
  if ($idVenta === '') throw new Exception('idVenta requerido');

  require_once __DIR__ . '/../config/firebase_init.php';
  $ROOT = 'projects/proj_8HNCM2DFob/data';
  $path = "$ROOT/VentasGenerales/$idVenta";

  $snap = $database->getReference($path)->getSnapshot();
  if (!$snap->exists()) throw new Exception('Venta no encontrada');

  $venta = $snap->getValue() ?: [];

  $out = [
    'TipoVenta'         => (string)($venta['TipoVenta'] ?? ''),
    'FechaInicio'       => (string)($venta['FechaInicio'] ?? ''),
    'FechaFinalizacion' => (string)($venta['FechaFinalizacion'] ?? ''),
    'ModalidadPagos'    => (string)($venta['ModalidadPagos'] ?? ''),
    'CantidadCuotas'    => (int)   ($venta['CantidadCuotas'] ?? 0),
    'NumeroContrato'    => (string)($venta['NumeroContrato'] ?? ''),
    'Enganche'          => (float) ($venta['Enganche'] ?? 0),
  ];

  echo json_encode(['ok'=>true, 'venta'=>$out], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
