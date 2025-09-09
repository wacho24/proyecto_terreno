<?php
// pages/config_general/cuentas_eliminar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase_init.php';

try {
  $raw = file_get_contents('php://input');
  $j   = json_decode($raw, true);
  if (!is_array($j)) throw new RuntimeException('JSON inválido.');

  $idDes = preg_replace('/[^A-Za-z0-9_\-]/','', (string)($j['idDesarrollo'] ?? ''));
  $id    = preg_replace('/[^A-Za-z0-9_\-]/','', (string)($j['id'] ?? ''));
  if ($idDes==='' || $id==='') throw new RuntimeException('Faltan parámetros.');

  $ROOT = 'projects/proj_8HNCM2DFob/data';
  $path = "$ROOT/DesarrollosGenerales/$idDes/Cuentas/$id";
  $database->getReference($path)->remove();

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
