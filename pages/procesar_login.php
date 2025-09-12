<?php
// pages/procesar_login.php
declare(strict_types=1);

session_start();

$DEBUG = false;                                // pon true para ver detalles en index.php
const DEFAULT_COMPANY_ID = 'proj_8HNCM2DFob';  // <-- tu company/tenant

function back_with_error(string $msg): void {
  $_SESSION['error_login'] = $msg;
  header('Location: ../index.php');
  exit;
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new RuntimeException('Método no permitido.');
  }

  $emailOrId = trim((string)($_POST['email'] ?? ''));
  $password  = (string)($_POST['password'] ?? '');

  if ($emailOrId === '' || $password === '') {
    throw new InvalidArgumentException('Debes ingresar correo/ID y contraseña.');
  }

  require_once __DIR__ . '/../config/firebase_init.php';
  /** @var \Kreait\Firebase\Auth $auth */
  /** @var \Kreait\Firebase\Database $database */

  // ---------- MODO A: AUTH NORMAL (email + password) ----------
  try {
    $signInResult = $auth->signInWithEmailAndPassword($emailOrId, $password);
    $uid = $signInResult->firebaseUserId();
    if (!$uid) {
      throw new RuntimeException('No se pudo obtener el UID del usuario.');
    }

    $companyId = DEFAULT_COMPANY_ID;
    $empPath   = "projects/{$companyId}/data/DesarrollosEmpresarios/{$uid}";
    $empSnap   = $database->getReference($empPath)->getSnapshot();

    if ($DEBUG) {
      $_SESSION['error_login'] =
        "DEBUG AUTH OK\n".
        "UID = {$uid}\n".
        "Ruta = {$empPath}\n".
        "Existe = ".($empSnap->exists() ? 'sí' : 'no')."\n".
        "Valor = ".var_export($empSnap->getValue(), true);
      header('Location: ../index.php'); exit;
    }

    if (!$empSnap->exists()) {
      back_with_error('Tu cuenta no está registrada como Empresario en el sistema.');
    }

    // Sesión mínima: rol viewer y acceso a su propio empresario
    $_SESSION['user_id']             = $uid;
    $_SESSION['email']               = strtolower($emailOrId);
    $_SESSION['nombre']              = (string)($empSnap->getValue()['NombreEmpresario'] ?? '');
    $_SESSION['rol']                 = 'viewer';
    $_SESSION['allowed_empresarios'] = [$uid];
    $_SESSION['allowed_projects']    = []; // si luego quieres limitar por proyectos, aquí
    $_SESSION['company_id']          = $companyId;

    unset($_SESSION['idDesarrollo']);

    header('Location: proyectos.php'); exit;

  } catch (\Kreait\Firebase\Auth\SignIn\FailedToSignIn $authError) {
    // ---------- MODO B: FALLBACK POR ID (sin Auth) ----------
    $enteredId = $emailOrId; // el usuario puede escribir su idEmpresario aquí
    $companyId = DEFAULT_COMPANY_ID;

    $empPath = "projects/{$companyId}/data/DesarrollosEmpresarios/{$enteredId}";
    $empSnap = $database->getReference($empPath)->getSnapshot();

    if ($DEBUG) {
      $_SESSION['error_login'] =
        "DEBUG ID MODE\n".
        "ID ingresado = {$enteredId}\n".
        "Ruta = {$empPath}\n".
        "Existe = ".($empSnap->exists() ? 'sí' : 'no')."\n".
        "Valor = ".var_export($empSnap->getValue(), true);
      header('Location: ../index.php'); exit;
    }

    if (!$empSnap->exists()) {
      back_with_error('Credenciales inválidas. Verifica tu correo, contraseña o tu ID de Empresario.');
    }

    // Sesión con ese ID (viewer)
    $_SESSION['user_id']             = $enteredId;
    $_SESSION['email']               = '';
    $_SESSION['nombre']              = (string)($empSnap->getValue()['NombreEmpresario'] ?? '');
    $_SESSION['rol']                 = 'viewer';
    $_SESSION['allowed_empresarios'] = [$enteredId];
    $_SESSION['allowed_projects']    = [];
    $_SESSION['company_id']          = $companyId;

    unset($_SESSION['idDesarrollo']);

    header('Location: proyectos.php'); exit;
  }

} catch (\Throwable $e) {
  back_with_error('Error en el inicio de sesión: '.$e->getMessage());
}
