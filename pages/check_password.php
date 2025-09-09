<?php
// pages/check_password.php
declare(strict_types=1);

session_start();

// --- Capturamos cualquier salida temprana (BOM/espacios/avisos) ---
ob_start();

header('Content-Type: application/json; charset=UTF-8');
error_reporting(0);               // Evita que avisos rompan el JSON
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/firebase_init.php'; // Debe exponer $auth (Kreait)

/**
 * TTL de la verificación de contraseña (segundos).
 * Mientras no expire, no volvemos a pedir contraseña.
 */
const PW_VERIFY_TTL = 15 * 60; // 15 minutos

function jres(array $data, int $code = 200): void {
  http_response_code($code);
  // --- Muy importante: limpiar cualquier salida previa (BOM/espacios) ---
  if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_clean();
  }
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// 1) Validar sesión (tu login ya guarda estas claves)
if (empty($_SESSION['idDesarrollo']) || empty($_SESSION['email'])) {
  jres(['ok' => false, 'error' => 'NOT_LOGGED_IN'], 401);
}

$email = (string)$_SESSION['email'];
$now   = time();

// 2) ¿Ya hay verificación vigente?
$until = (int)($_SESSION['pw_verified_until'] ?? 0);
if ($until > $now) {
  jres(['ok' => true, 'cached' => true, 'until' => $until], 200);
}

// 3) Leer password (soporta JSON y x-www-form-urlencoded)
$pwd = '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input') ?: '';

if (stripos($contentType, 'application/json') !== false) {
  $body = json_decode($raw, true);
  if (is_array($body)) $pwd = (string)($body['password'] ?? '');
} else {
  if (!empty($_POST['password'])) {
    $pwd = (string)$_POST['password'];
  } else {
    $tmp = [];
    parse_str($raw, $tmp);
    if (!empty($tmp['password'])) $pwd = (string)$tmp['password'];
  }
}

if ($pwd === '') {
  jres(['ok' => false, 'needPassword' => true, 'error' => 'PASSWORD_REQUIRED'], 401);
}

// 4) Verificar contra Firebase (mismo método que tu login)
try {
  /** @var \Kreait\Firebase\Auth $auth */
  $auth->signInWithEmailAndPassword($email, $pwd); // Lanza excepción si no coincide
  $_SESSION['pw_verified_until'] = $now + PW_VERIFY_TTL;

  jres([
    'ok'     => true,
    'cached' => false,
    'until'  => $_SESSION['pw_verified_until']
  ], 200);

} catch (\Kreait\Firebase\Auth\SignIn\FailedToSignIn $e) {
  $code = 'INVALID_CREDENTIALS';
  try {
    $errors = method_exists($e, 'errors') ? $e->errors() : [];
    $code = $errors['error']['message'] ?? $code; // p.ej. INVALID_PASSWORD, EMAIL_NOT_FOUND
  } catch (\Throwable $x) {}
  jres(['ok' => false, 'error' => $code], 401);

} catch (\Throwable $e) {
  jres(['ok' => false, 'error' => 'SERVER_ERROR'], 500);
}
