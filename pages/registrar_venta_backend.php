<?php
// pages/registrar_venta_backend.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

// ===== Firebase =====
require_once __DIR__ . '/../config/firebase_init.php';
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

// (opcional) límite para pre-calcular plan de pagos
const FECHAS_PAGO_LIMIT = 1;

/* ================= Helpers ================= */
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
function compute_end_iso($modalidad, $inicioISO, $cuotas){
  $inicioISO = trim((string)$inicioISO);
  if ($inicioISO === '' || !$cuotas || $cuotas <= 0) return '';
  try { $d = new DateTime($inicioISO.' 00:00:00'); } catch (Throwable $e) { return ''; }
  $steps = max(0, (int)$cuotas - 1);
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
function build_fechas_pago(string $modalidad, int $cuotas, string $inicioISO, float $montoPorCuota): array {
  $out = [];
  if ($modalidad === '' || $cuotas <= 0 || $inicioISO === '') return $out;
  try { $base = new DateTime($inicioISO.' 00:00:00'); } catch(Throwable $e){ return $out; }

  $max = max(0, min((int)$cuotas, FECHAS_PAGO_LIMIT));
  for ($i=0; $i<$max; $i++){
    $d = clone $base;
    switch (strtoupper($modalidad)) {
      case 'SEMANAL':        $d->modify('+' . (7*$i)  . ' day');   break;
      case 'QUINCENAL':      $d->modify('+' . (15*$i) . ' day');   break;
      case 'MENSUAL':        $d->modify('+' .  $i     . ' month'); break;
      case 'BIMESTRAL':      $d->modify('+' . (2*$i)  . ' month'); break;
      case 'TRIMESTRAL':     $d->modify('+' . (3*$i)  . ' month'); break;
      case 'CUATRIMESTRAL':  $d->modify('+' . (4*$i)  . ' month'); break;
      case 'SEMESTRAL':      $d->modify('+' . (6*$i)  . ' month'); break;
      case 'ANUAL':          $d->modify('+' . (12*$i) . ' month'); break;
      default:               $d->modify('+' .  $i     . ' month'); break;
    }
    $key = $d->format('Y-m-d');
    $out[$key] = ['Fecha'=>$key, 'Monto'=>round($montoPorCuota,2)];
  }
  return $out;
}
function parse_bank_label(string $label): array {
  $parts = array_map('trim', explode('—', $label));
  return [
    'Banco'        => $parts[0] ?? '',
    'Beneficiario' => $parts[1] ?? '',
    'CLABE'        => $parts[2] ?? '',
  ];
}
function is_b64($s){
  if ($s === '' || preg_match('/[^A-Za-z0-9+\/=]/', $s)) return false;
  $d = base64_decode($s, true);
  return $d !== false && base64_encode($d) === $s;
}

/* =================== MAIN =================== */
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

  $lotes    = $data['lotes'];        // [{id,label,precio}]
  $enganche = $data['enganche'] ?? [];

  // Totales/aux
  $precioTotal = 0.0;
  $nombresLote = [];
  $primerLoteId = '';
  $primerLoteLabel = '';
  $primerLotePrecio = 0.0;

  foreach ($lotes as $ix => $l) {
    $precio = normalize_money($l['precio'] ?? 0);
    $precioTotal += $precio;
    $nombresLote[] = (string)($l['label'] ?? ($l['id'] ?? ''));
    if ($ix === 0) {
      $primerLoteId     = (string)($l['id'] ?? '');
      $primerLoteLabel  = (string)($l['label'] ?? $primerLoteId);
      $primerLotePrecio = $precio;
    }
  }

  $estatusVenta = (strpos($tipo, 'CONTADO') !== false) ? 'LIQUIDADO' : 'PENDIENTE';

  $modalidad       = strtoupper((string)($enganche['modalidad'] ?? ''));
  $cantidadCuotas  = isset($enganche['cuotas']) ? (int)$enganche['cuotas'] : 0;
  $inicioISO       = (string)($enganche['inicio'] ?? '');
  $engancheTotal   = normalize_money($enganche['total'] ?? 0);
  $fechaFinISO     = ($modalidad && $cantidadCuotas>0 && $inicioISO) ? compute_end_iso($modalidad, $inicioISO, $cantidadCuotas) : '';
  $montoPorCuota   = ($modalidad && $cantidadCuotas>0) ? ($engancheTotal / max(1,$cantidadCuotas)) : 0.0;

  // Fechas personalizadas (opcional desde el front)
  $fechasPers = $enganche['fechasPersonalizadas'] ?? null;
  if (is_array($fechasPers) && !empty($fechasPers)) {
    $tmp = [];
    foreach ($fechasPers as $it) {
      $f = isset($it['Fecha']) ? (string)$it['Fecha'] : '';
      if ($f === '') continue;
      $m = normalize_money($it['Monto'] ?? 0);
      $tmp[$f] = ['Fecha'=>$f, 'Monto'=>$m];
      if (count($tmp) >= FECHAS_PAGO_LIMIT) break;
    }
    $fechasPagoObj = $tmp;
  } else {
    $fechasPagoObj = build_fechas_pago($modalidad, $cantidadCuotas, $inicioISO, $montoPorCuota);
  }

  /* ====== Guardar en Firebase ====== */
  $ventaId = '';
  try {
    $ventaRow = [
      'CuentaBancaria'   => (string)($enganche['cuenta'] ?? ''),
      'Enganche'         => $engancheTotal,
      'Estatus'          => $estatusVenta,
      'FechaFinalizacion'=> dmy($fechaFinISO),
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
      'Observacion'      => $obs,
      'idDesarrollo'     => $idDes,
    ];

    $ventasPath = "$ROOT_PREFIX/VentasGenerales";
    $ref = $database->getReference($ventasPath)->push($ventaRow);
    $ventaId = $ref->getKey();

    if ($ventaId && is_array($lotes) && count($lotes)) {
      $lotesPath = "$ventasPath/$ventaId/Lotes";
      $payloadLotes = [];
      foreach ($lotes as $l) {
        $lid = (string)($l['id'] ?? '');
        if ($lid === '') $lid = $database->getReference($lotesPath)->push()->getKey();
        $payloadLotes[$lid] = [
          'Label'  => (string)($l['label'] ?? $lid),
          'Precio' => normalize_money($l['precio'] ?? 0),
        ];
      }
      $database->getReference($lotesPath)->update($payloadLotes);
    }

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
  } catch (Throwable $e) {
    $ventaId = $ventaId ?: (string)round(microtime(true)*1000);
  }

  /* ====== Prepara payload para que el FRONT lo mande a Apphive ====== */
  $cuentaId   = (string)($enganche['cuentaId'] ?? '');
  $cuentaLbl  = (string)($enganche['cuenta'] ?? ''); // "Banco — Beneficiario — CLABE"
  $bankParts  = parse_bank_label($cuentaLbl);

  // idCliente en Base64 (si ya viene en b64, se respeta)
  $idClienteB64 = is_b64($cliente) ? $cliente : base64_encode($cliente);

  // Base con colecciones/objetos (Detalles y Cliente.idCliente en b64)
  $payloadApphive = [
    'ids' => [
      'idVenta'      => (string)($ventaId ?: $contrato),
      'idDesarrollo' => (string)$idDes,
    ],
    'Lote' => [
      'id'             => (string)$primerLoteId,
      'NombreLote'     => (string)$primerLoteLabel,
      'PrecioLote'     => (float)$primerLotePrecio,
      'idManzana'      => 'idManzana',
      'FotoDesarrollo' => '',
    ],
    'CuentaBancaria' => [
      'id'           => (string)$cuentaId,
      'Banco'        => $bankParts['Banco'],
      'CLABE'        => $bankParts['CLABE'],
      'Beneficiario' => $bankParts['Beneficiario'],
    ],
    'Vendedor' => [
      'id'             => (string)$asesorId,
      'NombreVendedor' => (string)$asesorRef,
    ],
    // ⬇⬇⬇ Renombrado a "Detalles"
    'Detalles' => [
      'Enganche'           => (float)$engancheTotal,
      'EstatusFiniquitado' => ($estatusVenta === 'LIQUIDADO') ? 'Liquidado' : 'Pendiente',
      'FechaFinalizacion'  => (string)$fechaFinISO,
      'FechaInicio'        => (string)$inicioISO,
      'FechaVenta'         => (string)$fechaISO,
      'ModalidadPagos'     => !empty($enganche['plan']) ? (string)$modalidad : '',
    ],
    'Cliente' => [
      'idCliente'     => (string)$idClienteB64,   // Base64
      'NombreCliente' => (string)$clienteLabel,
    ],
    // Objeto para que Apphive lo muestre como columnas de fecha
    'FechasPago' => (object)$fechasPagoObj,
  ];

  // ➕ Campos "planos" que Apphive/Producción necesita como columnas
  $payloadApphive['Banco']              = $bankParts['Banco'];
  $payloadApphive['BeneficiarioCuenta'] = $bankParts['Beneficiario'];
  $payloadApphive['idCuentaBancaria']   = (string)$cuentaId;

  $payloadApphive['NombreLote']   = (string)$primerLoteLabel;
  $payloadApphive['idLote']       = (string)$primerLoteId;
  $payloadApphive['idManzana']    = 'idManzana';
  $payloadApphive['PrecioLote']   = (float)$primerLotePrecio;

  $payloadApphive['NombreVendedor'] = (string)$asesorRef;
  $payloadApphive['idVendedor']     = (string)$asesorId;

  $payloadApphive['NumeroContrato'] = (string)$contrato;
  $payloadApphive['idVenta']        = (string)($ventaId ?: $contrato);
  $payloadApphive['idDesarrollo']   = (string)$idDes;

  $payloadApphive['Enganche']         = (float)$engancheTotal;
  $payloadApphive['Estatus']          = (string)$estatusVenta;
  $payloadApphive['ModalidadPagos']   = !empty($enganche['plan']) ? (string)$modalidad : '';
  $payloadApphive['FechaFinalizacion']= (string)$fechaFinISO;
  $payloadApphive['FechaInicio']      = (string)$inicioISO;
  $payloadApphive['FechaVenta']       = (string)$fechaISO;
  $payloadApphive['NombreCliente']    = (string)$clienteLabel;

  echo json_encode([
    'ok'              => true,
    'idVenta'         => $ventaId ?: $contrato,
    'payload_apphive' => $payloadApphive, // envíalo tal cual a enviar_webhook.php
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
