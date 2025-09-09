<?php
// pages/venta_editar.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true);
  if (!$in) throw new Exception('JSON inválido');

  $idVenta     = (string)($in['idVenta'] ?? '');
  $idDesarrollo= (string)($in['idDesarrollo'] ?? '');
  if ($idVenta === '' || $idDesarrollo === '') throw new Exception('Faltan parámetros');

  require_once __DIR__ . '/../config/firebase_init.php';
  $ROOT = 'projects/proj_8HNCM2DFob/data';
  $path = "$ROOT/DesarrollosGenerales/$idDesarrollo/Ventas/$idVenta";

  // Campos que guardaremos
  $tipo   = (string)($in['tipo'] ?? '');
  $status = (stripos($tipo,'CREDITO') !== false) ? 'PENDIENTE' : 'DISPONIBLE';

  $datos = [
    'clienteId'    => (string)($in['cliente'] ?? ''),
    'clienteLabel' => (string)($in['clienteLabel'] ?? ''),
    'fecha'        => (string)($in['fecha'] ?? ''),
    'tipo'         => $tipo,
    'estatus'      => $status,
    'contrato'     => (string)($in['contrato'] ?? ''),
    'obs'          => (string)($in['obs'] ?? ''),
    'enganche'     => $in['enganche'] ?? new stdClass(),
  ];

  // lotes + total
  $lotes = is_array($in['lotes'] ?? null) ? $in['lotes'] : [];
  $total = 0; foreach ($lotes as $l) { $total += floatval($l['precio'] ?? 0); }
  $datos['lotes'] = $lotes;
  $datos['total'] = $total;

  // write
  $database->getReference($path)->update($datos);

  echo json_encode(['ok'=>true, 'idVenta'=>$idVenta, 'estatus'=>$status, 'total'=>$total], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
