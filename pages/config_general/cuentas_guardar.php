<?php
// pages/config_general/cuentas_guardar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase_init.php';

try {
  $raw = file_get_contents('php://input');
  $j   = json_decode($raw, true);
  if (!is_array($j)) throw new RuntimeException('JSON inválido.');

  $idDes = (string)($j['idDesarrollo'] ?? '');
  $idDes = preg_replace('/[^A-Za-z0-9_\-]/','',$idDes);
  if ($idDes==='') throw new RuntimeException('Falta id del desarrollo.');

  $id      = (string)($j['id'] ?? '');
  $Banco   = trim((string)($j['Banco'] ?? ''));
  $Benef   = trim((string)($j['Beneficiario'] ?? ''));
  $CLABE   = trim((string)($j['CLABE'] ?? ''));
  $idCta   = trim((string)($j['idCuenta'] ?? ''));

  if ($Banco==='' || $Benef==='' || $CLABE==='') {
    throw new RuntimeException('Banco, Beneficiario y CLABE son obligatorios.');
  }

  $ROOT = 'projects/proj_8HNCM2DFob/data';
  $base = "$ROOT/DesarrollosGenerales/$idDes/Cuentas";

  $data = [
    'Banco'        => $Banco,
    'Beneficiario' => $Benef,
    'CLABE'        => $CLABE,
    'idCuenta'     => $idCta,
  ];

  if ($id==='') {
    // crear
    $database->getReference($base)->push($data);
  } else {
    // actualizar
    $database->getReference("$base/$id")->update($data);
  }

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
