<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
while (ob_get_level()) { ob_end_clean(); }
header_remove();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/_guard.php';
requireAuth(); // debe dejar $_SESSION['uid'] o similar

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']); exit;
    }

    $uid = $_SESSION['uid'] ?? null;               // Asegúrate de setear este valor tras el login
    if (!$uid) {                                   
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autenticado (uid)']); exit;
    }

    // Campos obligatorios
    $nombre    = trim($_POST['NombreDesarrollo'] ?? '');
    $direccion = trim($_POST['Direccion'] ?? '');
    if ($nombre === '' || $direccion === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Faltan campos obligatorios']); exit;
    }

    // Archivo (foto)
    if (
        empty($_FILES['foto']) ||
        $_FILES['foto']['error'] !== UPLOAD_ERR_OK ||
        !is_uploaded_file($_FILES['foto']['tmp_name'])
    ){
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'No se recibió la foto']); exit;
    }

    $file = $_FILES['foto'];
    $tmp  = $file['tmp_name'];
    $orig = $file['name'] ?? 'portada';
    $size = (int)($file['size'] ?? 0);

    // Validaciones básicas
    if ($size <= 0 || $size > 10 * 1024 * 1024) { // 10 MB
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Tamaño inválido (máx 10MB)']); exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp) ?: '';
    finfo_close($finfo);

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($extMap[$mime])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Formato no soportado: '.$mime]); exit;
    }
    $ext = $extMap[$mime];

    // Nombre de objeto en Storage (carpeta por usuario)
    $slugNombre = preg_replace('~[^a-z0-9]+~i', '-', $nombre);
    $path = sprintf('desarrollos/%s/%s_%s.%s',
        $uid,
        date('Ymd-His'),
        strtolower($slugNombre ?: 'portada'),
        $ext
    );

    // Subir al bucket con token de descarga
    $bucket = $storage->getBucket(); // el que configuramos en firebase_init.php
    $token  = bin2hex(random_bytes(16)); // token de descarga

    $object = $bucket->upload(
        fopen($tmp, 'r'),
        [
            'name'       => $path,
            'metadata'   => [
                // Necesario para URL pública tipo firebasestorage.googleapis.com
                'firebaseStorageDownloadTokens' => $token,
            ],
            'predefinedAcl' => 'private',     // Admin SDK ignora reglas del cliente; mantenemos privado + token
        ]
    );

    // URL pública con token (estilo Firebase)
    $bucketName = $bucket->name();
    $encoded    = rawurlencode($object->name());
    $publicUrl  = "https://firebasestorage.googleapis.com/v0/b/{$bucketName}/o/{$encoded}?alt=media&token={$token}";

    // Guardar en RTDB
    $ref  = $database->getReference("DesarrollosEmpresarios/{$uid}/Desarrollos")->push([
        'NombreDesarrollo' => $nombre,
        'Direccion'        => $direccion,
        'FotoPortada'      => $publicUrl,
        'ts'               => time(),
    ]);

    echo json_encode([
        'ok'        => true,
        'url'       => $publicUrl,
        'path'      => $path,
        'dbKey'     => $ref->getKey(),
        'bucket'    => $bucketName
    ]);
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    exit;
}
