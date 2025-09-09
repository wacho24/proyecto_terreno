<?php
// pages/procesar_login.php
declare(strict_types=1);
session_start();

$DEBUG = false; // <- ponlo en true solo para pruebas

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }

    $email    = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($email === '' || $password === '') {
        throw new InvalidArgumentException('Debes ingresar correo y contraseña.');
    }

    require_once __DIR__ . '/../config/firebase_init.php';
    /** @var \Kreait\Firebase\Auth $auth */
    /** @var \Kreait\Firebase\Database $database */

    // === 1) AUTH: email/clave ===
    $signInResult = $auth->signInWithEmailAndPassword($email, $password);
    $uid = $signInResult->firebaseUserId();
    if (!$uid) {
        throw new RuntimeException('No se pudo obtener el UID del usuario.');
    }

    // (Opcional) Exigir email verificado:
    // $userRecord = $auth->getUser($uid);
    // if (!$userRecord->emailVerified) {
    //     throw new RuntimeException('Debes verificar tu correo electrónico antes de continuar.');
    // }

    // === 2) VALIDACIÓN SOLICITADA: idEmpresario ===
    $path = "projects/proj_8HNCM2DFob/data/DesarrollosEmpresarios/{$uid}/idEmpresario";
    $idEmpresario = $database->getReference($path)->getValue();

    if ($DEBUG) {
        // Solo para debug local: ver lo que hay
        $_SESSION['error_login'] = "DEBUG → UID: {$uid} | idEmpresario leído: ".var_export($idEmpresario, true);
        header('Location: ../index.php'); exit;
    }

    if (empty($idEmpresario)) {
        $_SESSION['access_denied'] = 'No estás registrado como Empresario en el sistema.';
        header('Location: ../index.php'); exit;
    }

    if ((string)$idEmpresario !== (string)$uid) {
        $_SESSION['access_denied'] = 'Tu cuenta no está vinculada correctamente (idEmpresario no coincide).';
        header('Location: ../index.php'); exit;
    }

    // === 3) PASO: sesión y redirección ===
    $_SESSION['idDesarrollo'] = $uid;  // usas esto como flag
    $_SESSION['email']        = $email;

    header('Location: proyectos.php'); exit;

} catch (\Kreait\Firebase\Auth\SignIn\FailedToSignIn $e) {
    // Decodificar mensaje de Firebase para mostrar causa real
    $msg = 'Credenciales inválidas. Verifica tu correo y contraseña.'; // fallback
    try {
        $errors = method_exists($e, 'errors') ? $e->errors() : [];
        $code = $errors['error']['message'] ?? '';
        // Mapear códigos comunes
        switch ($code) {
            case 'EMAIL_NOT_FOUND':
            case 'USER_NOT_FOUND':
                $msg = 'El correo no está registrado.';
                break;
            case 'INVALID_PASSWORD':
                $msg = 'La contraseña es incorrecta.';
                break;
            case 'USER_DISABLED':
                $msg = 'Tu usuario está deshabilitado.';
                break;
            case 'TOO_MANY_ATTEMPTS_TRY_LATER':
                $msg = 'Demasiados intentos. Inténtalo más tarde.';
                break;
            default:
                if (!empty($code)) { $msg = "Error de autenticación: {$code}"; }
        }
    } catch (\Throwable $x) {}
    $_SESSION['error_login'] = $msg;
    header('Location: ../index.php'); exit;

} catch (\Throwable $e) {
    $_SESSION['error_login'] = $e->getMessage();
    header('Location: ../index.php'); exit;
}
