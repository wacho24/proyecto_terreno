<?php
// pages/enviar_cliente.php
require_once __DIR__ . '/_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
  // 1) lee JSON
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'EMPTY_BODY']); exit;
  }
  $body = json_decode($raw, true);
  if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'INVALID_JSON']); exit;
  }

  // 2) valida campos mínimos
  $idDesarrollo = isset($body['idDesarrollo']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$body['idDesarrollo']) : '';
  $cli          = $body['Cliente'] ?? null;
  $ben          = $body['Beneficiario'] ?? [];

  if ($idDesarrollo === '' || !is_array($cli)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'MISSING_FIELDS']); exit;
  }

  // 3) normaliza correo e ID de cliente (base64 del correo en minúsculas)
  $email = strtolower(trim((string)($cli['Email'] ?? '')));
  if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'INVALID_EMAIL']); exit;
  }
  $idCliente = isset($cli['id']) && $cli['id'] !== '' ? (string)$cli['id'] : base64_encode($email);

  // 4) mapa normalizado para guardar
  $cliRow = [
    'Calle'        => (string)($cli['Calle']        ?? ''),
    'CodigoPostal' => (string)($cli['CodigoPostal'] ?? ''),
    'Curp'         => (string)($cli['Curp']         ?? ''),
    'Email'        => $email,
    'Estado'       => (string)($cli['Estado']       ?? ''),
    'Municipio'    => (string)($cli['Municipio']    ?? ''),
    'Nombre'       => (string)($cli['Nombre']       ?? ''),
    'Pais'         => (string)($cli['Pais']         ?? ''),
    'Telefono'     => (string)($cli['Telefono']     ?? ''),
  ];

  $benefRow = [
    'Curp'       => (string)($ben['Curp']       ?? ''),
    'Email'      => strtolower(trim((string)($ben['Email'] ?? ''))),
    'Nombre'     => (string)($ben['Nombre']     ?? ''),
    'Parentesco' => (string)($ben['Parentesco'] ?? ''),
    'Telefono'   => (string)($ben['Telefono']   ?? ''),
    'FechaNac'   => (string)($ben['FechaNac']   ?? ''),
  ];

  // 5) Firebase
  require_once __DIR__ . '/../config/firebase_init.php';
  $ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

  // 5.1) Busca idDesarrollo interno (para rama Empresarios)
  $desarrolloIdCampo = '';
  try {
    $snapInfo = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo")->getSnapshot();
    $tmpInfo  = $snapInfo->getValue() ?: [];
    $desarrolloIdCampo = (string)($tmpInfo['idDesarrollo'] ?? '');
  } catch (Throwable $e0) {}

  // 6) Prepara escritura en lote
  $updates = [];

  // a) DesarrollosClientes
  $updates["$ROOT_PREFIX/DesarrollosClientes/$idDesarrollo/Clientes/$idCliente"] = $cliRow;

  // b) DesarrollosGenerales
  $updates["$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo/Clientes/$idCliente"] = $cliRow;

  // c) Empresarios (si hay id interno)
  if ($desarrolloIdCampo !== '') {
    $updates["$ROOT_PREFIX/Empresarios/$desarrolloIdCampo/Clientes/$idCliente"] = $cliRow;
  }

  // d) Beneficiarios (si quieres conservarlos)
  if (array_filter($benefRow)) {
    $updates["$ROOT_PREFIX/DesarrollosClientes/$idDesarrollo/Clientes/$idCliente/Beneficiarios/default"] = $benefRow;
    $updates["$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo/Clientes/$idCliente/Beneficiarios/default"] = $benefRow;
    if ($desarrolloIdCampo !== '') {
      $updates["$ROOT_PREFIX/Empresarios/$desarrolloIdCampo/Clientes/$idCliente/Beneficiarios/default"] = $benefRow;
    }
  }

  // 7) Ejecuta
  $database->getReference('/')->update($updates);

  echo json_encode([
    'ok' => true,
    'idDesarrollo' => $idDesarrollo,
    'idCliente'    => $idCliente,
    'paths'        => array_keys($updates)
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
