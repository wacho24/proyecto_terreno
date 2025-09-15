<?php
// pages/enviar_webhook.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ===== (nuevo) sesión y Firebase para resolver FotoDesarrollo si viene vacío =====
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/firebase_init.php';
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

// Helpers extra (no invasivos) para la foto
function __pickStorageUrl($val): string {
  if (is_string($val) && $val!=='') return $val;
  if (is_array($val)) {
    if (!empty($val['downloadUrl'])) return (string)$val['downloadUrl'];
    if (!empty($val['url']))         return (string)$val['url'];
  }
  return '';
}
function __getFotoDesarrolloExact($db, string $root, string $idEmpresario, string $idDesarrollo): string {
  $paths = [
    "$root/DesarrollosEmpresarios/$idEmpresario/Desarrollos/$idDesarrollo/FotoDesarrollo",
    "$root/DesarrollosGenerales/$idDesarrollo/FotoDesarrollo",
    "$root/Desarrollos/$idDesarrollo/FotoDesarrollo",
  ];
  foreach ($paths as $p) {
    try {
      $snap = $db->getReference($p)->getSnapshot();
      if (!$snap->exists()) continue;
      $url = __pickStorageUrl($snap->getValue());
      if ($url !== '') return $url;
    } catch (Throwable $e) { /* seguir */ }
  }
  return '';
}

/**
 * CONFIG: URL del Webhook de Apphive
 */
const APPHIVE_WEBHOOK_URL = 'https://editor.apphive.io/hook/ccp_pipgTradYMfgDRjRiJ5DbZ';

/**
 * Enviar SOLO las primeras N fechas de pago
 */
const FECHAS_PAGO_LIMIT = 0;


/* ===================== Helpers ===================== */
function jres(array $d, int $code = 200): void {
  http_response_code($code);
  echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function s($v): string { return trim((string)$v); }
function f($v): float {
  if (is_numeric($v)) return (float)$v;
  $t = preg_replace('/[^\d\.\-]/', '', (string)$v);
  return $t === '' ? 0.0 : (float)$t;
}
function is_b64($s){
  if ($s === '' || preg_match('/[^A-Za-z0-9+\/=]/', $s)) return false;
  $d = base64_decode($s, true);
  return $d !== false && base64_encode($d) === $s;
}

/**
 * Lee JSON crudo del body. Si te mandan form-data/URL-encoded, también mapea.
 */
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in) || !$in) {
  $in = $_POST ?: [];
}

/* ===================== Mapeo exacto al payload ===================== */

/* ---- ids ---- */
$ids = [
  'idVenta'      => s($in['ids']['idVenta']       ?? $in['idVenta']       ?? '' ),
  'idDesarrollo' => s($in['ids']['idDesarrollo']  ?? $in['idDesarrollo']  ?? '' ),
];

/* ---- Lote ---- */
$Lote = [
  'id'             => s($in['Lote']['id']             ?? $in['idLote']        ?? '' ),
  'NombreLote'     => s($in['Lote']['NombreLote']     ?? $in['NombreLote']    ?? '' ),
  'PrecioLote'     => f($in['Lote']['PrecioLote']     ?? $in['PrecioLote']    ?? 0  ),
  'idManzana'      => s($in['Lote']['idManzana']      ?? $in['idManzana']     ?? '' ),
  'FotoDesarrollo' => s($in['Lote']['FotoDesarrollo'] ?? $in['FotoDesarrollo']?? '' ),
];

/* ---- CuentaBancaria ---- */
$CuentaBancaria = [
  'id'           => s($in['CuentaBancaria']['id']           ?? $in['idCuenta']      ?? '' ),
  'Banco'        => s($in['CuentaBancaria']['Banco']        ?? $in['Banco']         ?? '' ),
  'CLABE'        => s($in['CuentaBancaria']['CLABE']        ?? $in['CLABE']         ?? '' ),
  'Beneficiario' => s($in['CuentaBancaria']['Beneficiario'] ?? $in['Beneficiario']  ?? '' ),
];

/* ---- Vendedor ---- */
$Vendedor = [
  'id'             => s($in['Vendedor']['id']             ?? $in['idVendedor']     ?? '' ),
  'NombreVendedor' => s($in['Vendedor']['NombreVendedor'] ?? $in['NombreVendedor'] ?? '' ),
];

/* ---- Detalles ----
 * Acepta "Detalles" o "DetallesVenta" y lo envía como "Detalles"
 */
$D = $in['Detalles'] ?? $in['DetallesVenta'] ?? [];
$Detalles = [
  'Enganche'           => f($D['Enganche']           ?? $in['Enganche']           ?? 0  ),
  'EstatusFiniquitado' => s($D['EstatusFiniquitado'] ?? $in['EstatusFiniquitado'] ?? '' ),
  'FechaFinalizacion'  => s($D['FechaFinalizacion']  ?? $in['FechaFinalizacion']  ?? '' ),
  'FechaInicio'        => s($D['FechaInicio']        ?? $in['FechaInicio']        ?? '' ),
  'FechaVenta'         => s($D['FechaVenta']         ?? $in['FechaVenta']         ?? '' ),
  'ModalidadPagos'     => s($D['ModalidadPagos']     ?? $in['ModalidadPagos']     ?? '' ),
  'MetodoPago'         => s($D['MetodoPago']         ?? $in['MetodoPago']         ?? '' ),
  'TipoVenta'          => s($D['TipoVenta']          ?? $in['TipoVenta']          ?? '' ), // ✅ NUEVO: incluir TipoVenta
];

/* ---- Cliente (idCliente en Base64) ---- */
$cliIdRaw = s($in['Cliente']['idCliente'] ?? $in['idCliente'] ?? '');
$cliIdB64 = is_b64($cliIdRaw) ? $cliIdRaw : base64_encode($cliIdRaw);
$Cliente = [
  'idCliente'     => $cliIdB64,
  'NombreCliente' => s($in['Cliente']['NombreCliente'] ?? $in['NombreCliente'] ?? '' ),
];

/* ---- FechasPago ---- */
$FechasPago = [];
if (isset($in['FechasPago']) && is_array($in['FechasPago'])) {
  $isAssoc = array_keys($in['FechasPago']) !== range(0, count($in['FechasPago']) - 1);
  if ($isAssoc) {
    foreach ($in['FechasPago'] as $k => $v) {
      $fecha = s($v['Fecha'] ?? $k);
      if ($fecha === '') continue;
      $FechasPago[$fecha] = [
        'Fecha' => $fecha,
        'Monto' => f($v['Monto'] ?? 0),
      ];
    }
  } else {
    foreach ($in['FechasPago'] as $item) {
      if (!is_array($item)) continue;
      $fecha = s($item['Fecha'] ?? '');
      if ($fecha === '') continue;
      $FechasPago[$fecha] = [
        'Fecha' => $fecha,
        'Monto' => f($item['Monto'] ?? 0),
      ];
    }
  }
}
/* ordenar por fecha y limitar a N */
if (!empty($FechasPago)) {
  ksort($FechasPago, SORT_STRING);
  if (FECHAS_PAGO_LIMIT > 0 && count($FechasPago) > FECHAS_PAGO_LIMIT) {
    $FechasPago = array_slice($FechasPago, 0, FECHAS_PAGO_LIMIT, true);
  }
}

/* ===================== Payload final ===================== */
$payload = [
  'ids'            => $ids,
  'Lote'           => $Lote,
  'CuentaBancaria' => $CuentaBancaria,
  'Vendedor'       => $Vendedor,
  'Detalles'       => $Detalles,          // nombre final: Detalles
  'Cliente'        => $Cliente,           // idCliente en Base64
  'FechasPago'     => (object)$FechasPago,
];

/* ===== (nuevo) completar FotoDesarrollo si viene vacío ===== */
try {
  $fotoActual = (string)($payload['Lote']['FotoDesarrollo'] ?? '');
  if ($fotoActual === '') {
    $idDes = (string)($payload['ids']['idDesarrollo'] ?? ($_SESSION['idDesarrollo'] ?? ''));
    $idEmp = (string)($_SESSION['user_id'] ?? '');
    if ($idDes !== '') {
      $foto = __getFotoDesarrolloExact($database, $ROOT_PREFIX, $idEmp, $idDes);
      if ($foto !== '') {
        $payload['Lote']['FotoDesarrollo'] = $foto;
      }
    }
  }
} catch (Throwable $e) {
  // No interrumpimos el flujo si falla; simplemente se enviará vacío.
}

/* ===================== Envío a Apphive (cURL) ===================== */
try {
  $ch = curl_init(APPHIVE_WEBHOOK_URL);
  if ($ch === false) { jres(['status'=>'error','message'=>'No se pudo iniciar cURL'], 500); }

  $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 25,
  ]);

  $respBody = curl_exec($ch);
  $err      = curl_error($ch);
  $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($respBody === false) {
    jres([
      'status'  => 'error',
      'message' => 'Fallo al llamar Webhook',
      'curl'    => $err,
      'sent'    => $payload,
    ], 502);
  }

  $respJson = json_decode($respBody, true);
  jres([
    'status'       => ($code >= 200 && $code < 300) ? 'ok' : 'error',
    'http_code'    => $code,
    'apphive_raw'  => $respBody,
    'apphive_json' => is_array($respJson) ? $respJson : null,
    'sent'         => $payload,
  ], ($code >= 200 && $code < 300) ? 200 : 502);

} catch (Throwable $e) {
  jres([
    'status'  => 'error',
    'message' => 'Excepción al enviar',
    'error'   => $e->getMessage(),
    'sent'    => $payload,
  ], 500);
}
