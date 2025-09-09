<?php
// pages/eliminar_manzana_backend.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/firebase_init.php';

header('Content-Type: application/json; charset=UTF-8');

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

/* ---------------------------- helpers ---------------------------- */

function jres(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function sid(string $v): string {
  return preg_replace('/[^A-Za-z0-9_\-]/', '', $v);
}

/* ------------------------------ main ----------------------------- */

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jres(['status' => 'error', 'message' => 'Método no permitido'], 405);
  }

  // === Entradas ===
  $desarrolloRecordId = sid((string)($_POST['desarrolloRecordId'] ?? ''));
  $manzanaId          = sid((string)($_POST['manzanaId'] ?? ''));
  $password           = (string)($_POST['password'] ?? '');

  if ($desarrolloRecordId === '' || $manzanaId === '' || $password === '') {
    jres(['status' => 'error', 'message' => 'Faltan parámetros'], 422);
  }

  // === Sesión: email del usuario autenticado (tu login lo guarda como $_SESSION['email']) ===
  $email = (string)(
    $_SESSION['email']
    ?? $_SESSION['user_email']
    ?? $_SESSION['auth_email']
    ?? ''
  );

  if ($email === '') {
    jres([
      'status' => 'error',
      'message' => 'Sesión inválida: no se encontró email en la sesión.',
      'auth_error_code' => 'SESSION_EMAIL_MISSING'
    ], 401);
  }

  /** @var \Kreait\Firebase\Auth $auth */
  /** @var \Kreait\Firebase\Database $database */
  global $auth, $database;

  // === Revalidar contraseña usando el MISMO flujo que tu login ===
  try {
    $auth->signInWithEmailAndPassword($email, $password); // Si falla, lanza excepción
  } catch (\Kreait\Firebase\Auth\SignIn\FailedToSignIn $e) {
    // Decodificar el código real de Firebase para mostrarlo
    $code = '';
    try {
      $errors = method_exists($e, 'errors') ? $e->errors() : [];
      $code = $errors['error']['message'] ?? '';
    } catch (\Throwable $x) {}
    if ($code === '') { $code = 'INVALID_CREDENTIALS'; }

    // Puedes mapear a mensajes más amigables si quieres, aquí devolvemos el código crudo
    jres([
      'status' => 'error',
      'message' => 'No autorizado',
      'auth_error_code' => $code
    ], 401);
  }

  // === Verificar existencia de la manzana en Generales ===
  $refGen  = "$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId/Manzanas/$manzanaId";
  $exists  = $database->getReference($refGen)->getSnapshot()->exists();
  if (!$exists) {
    jres(['status' => 'error', 'message' => 'La manzana no existe'], 404);
  }

  // === Obtener idDesarrollo para replicar en Empresarios (si corresponde) ===
  $refInfo       = "$ROOT_PREFIX/DesarrollosGenerales/$desarrolloRecordId";
  $info          = $database->getReference($refInfo)->getValue() ?: [];
  $desarrolloId  = (string)($info['idDesarrollo'] ?? '');

  // === Multi-path delete: null => borra ===
  $updates = [];
  $updates[$refGen] = null;

  $replicadoEmpresario = false;
  if ($desarrolloId !== '') {
    $refEmp = "$ROOT_PREFIX/Empresarios/$desarrolloId/Manzanas/$manzanaId";
    $updates[$refEmp] = null;
    $replicadoEmpresario = true;
  }

  $database->getReference('/')->update($updates);

  jres([
    'status' => 'ok',
    'desarrolloRecordId' => $desarrolloRecordId,
    'idManzana' => $manzanaId,
    'replicadoEmpresario' => $replicadoEmpresario,
    'desarrolloId' => $desarrolloId
  ], 200);

} catch (\Throwable $e) {
  jres(['status' => 'error', 'message' => $e->getMessage()], 500);
}
