<?php
// pages/generales/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase_init.php';

try {
  $id = isset($_GET['id']) ? (string)$_GET['id'] : '';
  $id = preg_replace('/[^A-Za-z0-9_\-]/','',$id);
  if ($id==='') throw new RuntimeException('Falta id del desarrollo.');

  $ROOT = 'projects/proj_8HNCM2DFob/data';

  // ----- ¿Solo cuentas? -----
  if (isset($_GET['cuentas'])) {
    $path = "$ROOT/DesarrollosGenerales/$id/Cuentas";
    $rows = $database->getReference($path)->getValue() ?: [];
    $items = [];
    foreach ($rows as $rid => $r) {
      $items[] = [
        'id'           => (string)$rid,
        'banco'        => (string)($r['Banco']        ?? $r['bank']        ?? ''),
        'beneficiario' => (string)($r['Beneficiario'] ?? $r['holder']      ?? ''),
        'clabe'        => (string)($r['CLABE']        ?? $r['clabe']       ?? ''),
        'idCuenta'     => (string)($r['idCuenta']     ?? $r['accountId']   ?? ''),
      ];
    }
    echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // ----- Buscar el desarrollo bajo DesarrollosEmpresarios/*/Desarrollos/{id}
  $empRoot = "$ROOT/DesarrollosEmpresarios";
  $empSnap = $database->getReference($empRoot)->getSnapshot();
  $empAll  = $empSnap->getValue() ?: [];
  $found   = null;

  if (is_array($empAll)) {
    foreach ($empAll as $empId => $emp) {
      if (isset($emp['Desarrollos'][$id]) && is_array($emp['Desarrollos'][$id])) {
        $found = $emp['Desarrollos'][$id];
        $found['_empresarioId'] = $empId;
        break;
      }
    }
  }

  // Fallback por si también existe en Generales:
  if (!$found) {
    $gen = $database->getReference("$ROOT/DesarrollosGenerales/$id")->getValue() ?: [];
    if ($gen) $found = $gen;
  }

  if (!$found) throw new RuntimeException('No se encontró el desarrollo en la BD.');

  echo json_encode(['ok'=>true,'item'=>$found], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
