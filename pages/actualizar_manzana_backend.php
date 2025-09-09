<?php
// pages/actualizar_manzana_backend.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/firebase_init.php';

header('Content-Type: application/json; charset=UTF-8');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status'=>'error','message'=>'Método no permitido']);
    exit;
  }

  $ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

  // --- Entradas ---
  $desarrolloRecordId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_POST['desarrolloRecordId'] ?? ''));
  $manzanaId          = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_POST['manzanaId'] ?? ''));
  $nombreManzana      = trim((string)($_POST['nombreManzana'] ?? ''));

  if ($desarrolloRecordId === '' || $manzanaId === '' || $nombreManzana === '') {
    http_response_code(422);
    echo json_encode(['status'=>'error','message'=>'Faltan parámetros']);
    exit;
  }

  // Verificar que exista
  $refGen = "$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId/Manzanas/$manzanaId";
  $exists = $database->getReference($refGen)->getSnapshot()->exists();
  if (!$exists) {
    http_response_code(404);
    echo json_encode(['status'=>'error','message'=>'La manzana no existe']);
    exit;
  }

  // Obtener idDesarrollo para replicar en Empresarios (si está configurado)
  $refInfo = "$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId";
  $snapInfo = $database->getReference($refInfo)->getSnapshot();
  $info = $snapInfo->getValue() ?: [];
  $desarrolloId = (string)($info['idDesarrollo'] ?? '');

  // Construir multi-update
  $updates = [];
  $payload = ['NombreManzana' => $nombreManzana];

  // Generales
  $updates["$refGen"] = array_merge(($database->getReference($refGen)->getValue() ?: []), $payload);

  // Empresarios (si hay idDesarrollo): usamos MISMO key de manzana
  $replicadoEmpresario = false;
  if ($desarrolloId !== '') {
    $refEmp = "$ROOT_PREFIX/Empresarios/$desarrolloId/Manzanas/$manzanaId";
    $prevEmp = $database->getReference($refEmp)->getValue() ?: [];
    $updates["$refEmp"] = array_merge($prevEmp, $payload);
    $replicadoEmpresario = true;
  }

  $database->getReference('/')->update($updates);

  echo json_encode([
    'status' => 'ok',
    'desarrolloRecordId' => $desarrolloRecordId,
    'idManzana' => $manzanaId,
    'nombreManzana' => $nombreManzana,
    'replicadoEmpresario' => $replicadoEmpresario,
    'desarrolloId' => $desarrolloId
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
