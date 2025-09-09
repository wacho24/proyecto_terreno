<?php
// set_desarrollo.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
if ($id === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Falta id del desarrollo']);
  exit;
}

// Guarda el id del DESARROLLO (no del empresario) para que lo lea dashboard.php
$_SESSION['idDesarrollo'] = $id;

/* Opcional: ir acumulando ids seleccionados */
if (!isset($_SESSION['idsDesarrollo']) || !is_array($_SESSION['idsDesarrollo'])) {
  $_SESSION['idsDesarrollo'] = [];
}
$_SESSION['idsDesarrollo'][$id] = true;

echo json_encode(['ok'=>true,'id'=>$id]);
