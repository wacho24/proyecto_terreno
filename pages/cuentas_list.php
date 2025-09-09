<?php
// pages/cuentas_list.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/firebase_init.php';

try {
  $id = isset($_GET['id']) ? (string)$_GET['id'] : '';
  $id = preg_replace('/[^A-Za-z0-9_\-]/','',$id);
  if ($id==='') throw new RuntimeException('Falta id del desarrollo.');

  $ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';
  $path = "$ROOT_PREFIX/DesarrollosGenerales/$id/Cuentas";

  $snap = $database->getReference($path)->getSnapshot();
  $rows = $snap->getValue() ?: [];

  $items = [];
  foreach ($rows as $rid => $r) {
    $items[] = [
      'id'           => (string)$rid,
      'banco'        => (string)($r['Banco']        ?? $r['bank']        ?? ''),
      'beneficiario' => (string)($r['Beneficiario'] ?? $r['holder']      ?? ''),
      'clabe'        => (string)($r['CLABE']        ?? $r['clabe']       ?? $r['Numero'] ?? ''),
      'idCuenta'     => (string)($r['idCuenta']     ?? $r['accountId']   ?? ''),
    ];
  }

  echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
