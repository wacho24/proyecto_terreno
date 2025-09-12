<?php
// pages/pagar_detalle.php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/firebase_init.php';

header('Content-Type: application/json; charset=utf-8');

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

// --- util para leer JSON robusto ---
function read_json_body(): array {
  $raw = file_get_contents('php://input') ?: '';
  $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM
  $raw = trim($raw);
  if ($raw === '') return [];
  $i = strpos($raw, '{'); $j = strrpos($raw, '}');
  if ($i !== false && $j !== false) { $raw = substr($raw, $i, $j-$i+1); }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

try {
  $in = read_json_body();
  $idVenta = isset($in['idVenta']) ? (string)$in['idVenta'] : '';
  if ($idVenta === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'idVenta vacío']);
    exit;
  }

  // --- Venta (para contrato/total/tipo/cliente)
  $ventaPath = "$ROOT_PREFIX/VentasGenerales/$idVenta";
  $ventaSnap = $database->getReference($ventaPath)->getSnapshot();
  $venta     = $ventaSnap->getValue() ?: [];

  // normalizaciones
  $totalVenta = (float)($venta['PrecioLote'] ?? $venta['Total'] ?? 0);
  $contrato   = (string)($venta['NumeroContrato'] ?? $venta['Contrato'] ?? '');
  $cliente    = (string)($venta['NombreCliente'] ?? $venta['Cliente'] ?? '');
  $tipoVenta  = (string)($venta['TipoVenta'] ?? '');

  // --- Pagos: PagosRealizados/{idVenta}/PagosRealizados/*
  $headPath   = "$ROOT_PREFIX/PagosRealizados/$idVenta";
  $headSnap   = $database->getReference($headPath)->getSnapshot();
  $headNode   = $headSnap->getValue() ?: [];

  // idLote guardado en la cabecera
  $idLote     = (string)($headNode['idLote'] ?? '');

  $pagosNode  = $database->getReference("$headPath/PagosRealizados")->getSnapshot()->getValue() ?: [];
  $pagos      = [];

  $abonado = 0.0;
  foreach ($pagosNode as $pid => $p) {
    $item = [
      'id'          => $pid,
      'Comprobante' => (string)($p['Comprobante'] ?? ''),
      'Estatus'     => (string)($p['Estatus'] ?? ''),
      'FechaPago'   => (string)($p['FechaPago'] ?? ''),
      'FormaPago'   => (string)($p['FormaPago'] ?? ''),
      'Referencia'  => (string)($p['Referencia'] ?? ''),
      'Total'       => (float) ($p['Total'] ?? 0),
      'ts'          => (int)   ($p['ts'] ?? 0),
    ];
    $abonado += (float)$item['Total'];
    $pagos[] = $item;
  }

  // ordenar por ts/fecha descendente
  usort($pagos, function($a,$b){
    $ta = (int)($a['ts'] ?? 0); $tb = (int)($b['ts'] ?? 0);
    if ($ta && $tb) return $tb <=> $ta;
    return strcmp((string)($b['FechaPago'] ?? ''),(string)($a['FechaPago'] ?? ''));
  });

  $resta = max($totalVenta - $abonado, 0);

  echo json_encode([
    'ok'          => true,
    'ventaId'     => $idVenta,
    'contrato'    => $contrato,
    'cliente'     => $cliente,
    'tipoVenta'   => $tipoVenta,
    'idLote'      => $idLote,            // ahora sí devuelve el id/etiqueta que guardamos
    'totalVenta'  => $totalVenta,
    'abonado'     => $abonado,
    'restante'    => $resta,
    'pagos'       => $pagos
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
