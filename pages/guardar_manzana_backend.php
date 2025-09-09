<?php
// pages/guardar_manzana_backend.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
  // ---- Entradas ----
  $nombreManzana      = isset($_POST['nombreManzana']) ? trim((string)$_POST['nombreManzana']) : '';
  $desarrolloRecordId = isset($_POST['desarrolloRecordId']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$_POST['desarrolloRecordId']) : '';
  $replicarEmpresario = !empty($_POST['replicarEmpresario']);
  $desarrolloId       = isset($_POST['desarrolloId']) ? trim((string)$_POST['desarrolloId']) : '';

  if ($nombreManzana === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nombre de manzana requerido']); exit;
  }
  if ($desarrolloRecordId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Record id del desarrollo requerido']); exit;
  }

  // ---- Firebase ----
  require_once __DIR__ . '/../config/firebase_init.php';
  $ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

  // Ruta principal: DesarrollosGenerales/{RecordId}/Manzanas/{pushId}
  $pathGen = "$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId/Manzanas";
  $refNew  = $database->getReference($pathGen)->push();
  $pushId  = $refNew->getKey();

  // Guarda con idManzana = pushId (Record id)
  $payload = [
    'idManzana'     => $pushId,
    'NombreManzana' => $nombreManzana,
    'createdAt'     => date('c'),
    'createdBy'     => isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : 'web',
  ];
  $refNew->set($payload);

  // ---- Réplica opcional a DesarrollosEmpresarios ----
  $replicadoEmpresario = false;
  $replicaEn = null;

  if ($replicarEmpresario && $desarrolloId !== '') {
    $empRootSnap = $database->getReference("$ROOT_PREFIX/DesarrollosEmpresarios")->getSnapshot();
    $empresas = $empRootSnap->getValue() ?: [];

    foreach ($empresas as $empId => $empData) {
      if (empty($empData['Desarrollos']) || !is_array($empData['Desarrollos'])) continue;

      foreach ($empData['Desarrollos'] as $empDesRecId => $empDes) {
        if (isset($empDes['idDesarrollo']) && (string)$empDes['idDesarrollo'] === (string)$desarrolloId) {
          $pathEmp = "$ROOT_PREFIX/DesarrollosEmpresarios/$empId/Desarrollos/$empDesRecId/Manzanas";
          $refEmp  = $database->getReference($pathEmp)->push();
          $empPushId = $refEmp->getKey();

          // En la réplica, idManzana debe coincidir con el Record id de ESA réplica
          $payloadReplica = $payload;
          $payloadReplica['idManzana'] = $empPushId;

          $refEmp->set($payloadReplica);
          $replicadoEmpresario = true;
          $replicaEn = [
            'empresarioRecordId' => $empId,
            'desarrolloRecordId' => $empDesRecId,
          ];
          break 2;
        }
      }
    }
  }

  echo json_encode([
    'status'               => 'ok',
    'idManzana'            => $pushId,              // útil para tu UI
    'nombreManzana'        => $nombreManzana,
    'desarrolloRecordId'   => $desarrolloRecordId,
    'replicadoEmpresario'  => $replicadoEmpresario,
    'replicaEn'            => $replicaEn,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
