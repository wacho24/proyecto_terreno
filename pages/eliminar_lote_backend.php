<?php
// pages/eliminar_lote_backend.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/firebase_init.php';

header('Content-Type: application/json; charset=UTF-8');

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

/* -------------------- helpers -------------------- */
function jres(array $data, int $code=200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function sid(string $v): string { return preg_replace('/[^A-Za-z0-9_\-]/','',$v); }

/* --------------------- main ---------------------- */
try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jres(['status'=>'error','message'=>'Método no permitido'],405);
  }

  $desarrolloRecordId = sid((string)($_POST['desarrolloRecordId'] ?? ''));
  $loteId             = sid((string)($_POST['loteId'] ?? ''));
  $password           = (string)($_POST['password'] ?? '');

  if ($desarrolloRecordId === '' || $loteId === '' || $password === '') {
    jres(['status'=>'error','message'=>'Faltan parámetros'],422);
  }

  // email desde la sesión (igual que en eliminar_manzana_backend.php)
  $email = (string)(
    $_SESSION['email']
    ?? $_SESSION['user_email']
    ?? $_SESSION['auth_email']
    ?? ''
  );
  if ($email === '') {
    jres([
      'status'=>'error',
      'message'=>'Sesión inválida: no se encontró email en la sesión.',
      'auth_error_code'=>'SESSION_EMAIL_MISSING'
    ],401);
  }

  /** @var \Kreait\Firebase\Auth $auth */
  /** @var \Kreait\Firebase\Database $database */
  global $auth, $database;

  // Revalidar contraseña
  try {
    $auth->signInWithEmailAndPassword($email, $password);
  } catch (\Kreait\Firebase\Auth\SignIn\FailedToSignIn $e) {
    $code = '';
    try {
      $errors = method_exists($e,'errors') ? $e->errors() : [];
      $code = $errors['error']['message'] ?? '';
    } catch (\Throwable $x) {}
    if ($code === '') $code = 'INVALID_CREDENTIALS';
    jres(['status'=>'error','message'=>'No autorizado','auth_error_code'=>$code],401);
  }

  // Verificar existencia del lote
  $refGen = "$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId/Lotes/$loteId";
  if (!$database->getReference($refGen)->getSnapshot()->exists()) {
    jres(['status'=>'error','message'=>'El lote no existe'],404);
  }

  // Obtener idDesarrollo para replicar
  $info = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId")->getValue() ?: [];
  $idDesarrollo = (string)($info['idDesarrollo'] ?? '');

  // Multi-path delete
  $updates = [ $refGen => null ];
  $replicadoEmpresario = false;

  if ($idDesarrollo !== '') {
    $refEmp = "$ROOT_PREFIX/Empresarios/$idDesarrollo/Lotes/$loteId";
    $updates[$refEmp] = null;
    $replicadoEmpresario = true;
  }

  $database->getReference('/')->update($updates);

  jres([
    'status' => 'ok',
    'desarrolloRecordId' => $desarrolloRecordId,
    'loteId' => $loteId,
    'replicadoEmpresario' => $replicadoEmpresario,
    'desarrolloId' => $idDesarrollo
  ]);

} catch (\Throwable $e) {
  jres(['status'=>'error','message'=>$e->getMessage()],500);
}
