<?php
// pages/registrar_venta_backend.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/firebase_init.php';
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

function dmy($iso){
  $iso = trim((string)$iso);
  if ($iso === '') return '';
  $t = strtotime($iso);
  if ($t === false) return $iso;
  return date('d/m/Y', $t);
}
function normalize_money($v){
  if (is_numeric($v)) return (float)$v;
  $s = trim((string)$v);
  $s = preg_replace('/[^\d,\.\-]/', '', $s);
  if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
    if (strrpos($s, ',') > strrpos($s, '.')) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    else { $s = str_replace(',', '', $s); }
  } elseif (strpos($s, ',') !== false) {
    if (preg_match('/,\d{1,2}$/', $s)) { $s = str_replace(',', '.', $s); }
    else { $s = str_replace(',', '', $s); }
  }
  return (float)$s;
}

// ====== cálculo de fecha fin en el servidor ======
function compute_end_iso($modalidad, $inicioISO, $cuotas){
  $inicioISO = trim((string)$inicioISO);
  if ($inicioISO === '' || !$cuotas || $cuotas <= 0) return '';
  try {
    $d = new DateTime($inicioISO.' 00:00:00');
  } catch (Throwable $e) { return ''; }

  $steps = max(0, (int)$cuotas - 1); // último vencimiento
  $mod = strtoupper((string)$modalidad);

  for ($i=0; $i<$steps; $i++){
    switch ($mod) {
      case 'SEMANAL':        $d->modify('+7 day'); break;
      case 'QUINCENAL':      $d->modify('+15 day'); break;
      case 'MENSUAL':        $d->modify('+1 month'); break;
      case 'BIMESTRAL':      $d->modify('+2 month'); break;
      case 'TRIMESTRAL':     $d->modify('+3 month'); break;
      case 'CUATRIMESTRAL':  $d->modify('+4 month'); break;
      case 'SEMESTRAL':      $d->modify('+6 month'); break;
      case 'ANUAL':          $d->modify('+12 month'); break;
      default:               $d->modify('+1 month'); break;
    }
  }
  return $d->format('Y-m-d');
}

try{
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new Exception('Sin cuerpo');
  $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  foreach (['idDesarrollo','cliente','fecha','tipo','lotes'] as $k){
    if (!isset($data[$k]) || ($k!=='lotes' && trim((string)$data[$k])==='') || ($k==='lotes' && !is_array($data[$k]) )) {
      throw new Exception("Campo faltante o inválido: $k");
    }
  }

  $idDes       = preg_replace('/[^A-Za-z0-9_\-]/', '', $data['idDesarrollo']);
  $cliente     = (string)$data['cliente'];
  $clienteLabel= (string)($data['clienteLabel'] ?? $cliente);
  $fechaISO    = (string)$data['fecha'];               // YYYY-MM-DD
  $tipo        = strtoupper((string)$data['tipo']);    // A CONTADO / A CREDITO
  $contrato    = (string)($data['contrato'] ?? '');
  if ($contrato === '' || preg_match('/^por\s*generar$/i', $contrato)) {
    $contrato = (string)round(microtime(true)*1000);
  }
  $obs         = (string)($data['obs'] ?? '');

  // Vendedor (del login)
  $asesorId = (string)($data['asesorId'] ?? '');
  $asesorRef= (string)($data['asesorRef'] ?? '');

  $lotes  = $data['lotes'];        // [{id,label,precio}]
  $enganche = $data['enganche'] ?? [];

  // Totales
  $precioTotal = 0.0;
  $nombresLote = [];
  $primerLoteId = '';
  foreach ($lotes as $ix => $l) {
    $precio = normalize_money($l['precio'] ?? 0);
    $precioTotal += $precio;
    $nombresLote[] = (string)($l['label'] ?? ($l['id'] ?? ''));
    if ($ix === 0) $primerLoteId = (string)($l['id'] ?? '');
  }

  // Estatus por tipo
  $estatusVenta = (strpos($tipo, 'CONTADO') !== false) ? 'LIQUIDADO' : 'PENDIENTE';

  $modalidad = strtoupper((string)($enganche['modalidad'] ?? ''));
  $cantidadCuotas = isset($enganche['cuotas']) ? (int)$enganche['cuotas'] : 0;
  $inicioISO      = (string)($enganche['inicio'] ?? '');
  $fechaFinISO    = ($modalidad && $cantidadCuotas>0 && $inicioISO) ? compute_end_iso($modalidad, $inicioISO, $cantidadCuotas) : '';

  $ventaRow = [
    'CuentaBancaria'   => (string)($enganche['cuenta'] ?? ''),
    'Enganche'         => normalize_money($enganche['total'] ?? 0),
    'Estatus'          => $estatusVenta,
    'FechaFinalizacion'=> dmy($fechaFinISO),                 // calculado servidor
    'FechaInicio'      => dmy($inicioISO),
    'FechaVenta'       => dmy($fechaISO),
    'ModalidadPagos'   => $modalidad,
    'CantidadCuotas'   => $cantidadCuotas,
    'PlanEnganche'     => !empty($enganche['plan']) ? 1 : 0,
    'NombreCliente'    => $clienteLabel,
    'NombreLote'       => implode(', ', $nombresLote),
    'NombreVendedor'   => $asesorRef,
    'NumeroContrato'   => $contrato,
    'PrecioLote'       => $precioTotal,
    'TipoVenta'        => $tipo,
    'idCliente'        => $cliente,
    'idVendedor'       => $asesorId,
    'idLote'           => $primerLoteId,
    'Observacion'      => (string)($data['obs'] ?? ''),
    'idDesarrollo'     => $idDes,
  ];

  // Crear venta
  $ventasPath = "$ROOT_PREFIX/VentasGenerales";
  $ref = $database->getReference($ventasPath)->push($ventaRow);
  $ventaId = $ref->getKey();

  // Subcolección: Lotes
  if ($ventaId && is_array($lotes) && count($lotes)) {
    $lotesPath = "$ventasPath/$ventaId/Lotes";
    $payloadLotes = [];
    foreach ($lotes as $l) {
      $lid = (string)($l['id'] ?? '');
      if ($lid === '') $lid = $database->getReference($lotesPath)->push()->getKey(); // fallback
      $payloadLotes[$lid] = [
        'Label'  => (string)($l['label'] ?? $lid),
        'Precio' => normalize_money($l['precio'] ?? 0),
      ];
    }
    $database->getReference($lotesPath)->update($payloadLotes);
  }

  // Pagos (enganche)
  $engancheTotal = normalize_money($enganche['total'] ?? 0);
  if ($ventaId && $engancheTotal > 0) {
    $pagosPath = "$ventasPath/$ventaId/Pagos";
    $pagoRow = [
      'ComprobantePago' => '',
      'Descripcion'     => 'Enganche inicial',
      'Estatus'         => $estatusVenta,
      'FechaPago'       => dmy($fechaISO),
      'FechaRealizado'  => dmy($fechaISO),
      'Monto'           => $engancheTotal,
    ];
    $database->getReference($pagosPath)->push($pagoRow);
  }

  echo json_encode(['ok'=>true, 'idVenta'=>$ventaId], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
