<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
    // === DEBUG EN LOCAL ===
    ini_set('display_errors', '1');
    error_reporting(E_ALL);

    // Log a /logs/upload_error.log (asegúrate que la carpeta exista y tenga permisos de escritura)
    $logFile = __DIR__ . '/../logs/upload_error.log';
    function up_log(string $msg) {
        global $logFile;
        @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
    }

    require_once __DIR__ . '/../config/firebase_init.php';
    $bucket = fb_bucket();
    up_log('Bucket: '.$bucket->name());

    // Aceptar 'portada' o 'file'
    $field = null;
    if (!empty($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
        $field = $_FILES['portada'];
    } elseif (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $field = $_FILES['file'];
    } else {
        up_log('FILES recibido: '.json_encode(array_keys($_FILES)));
        throw new RuntimeException('No se recibió archivo válido. Usa el campo "portada".');
    }

    $tmp  = $field['tmp_name'];
    $name = $field['name'];
    $size = (int)$field['size'];

    // Tamaño
    $MAX = 8 * 1024 * 1024;
    if ($size <= 0 || $size > $MAX) {
        throw new RuntimeException('El archivo es demasiado grande (máx. 8MB).');
    }

    // MIME
    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp) ?: $mime;
    } elseif (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($tmp) ?: $mime;
    }
    $permitidos = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $permitidos, true)) {
        throw new RuntimeException('Tipo no permitido. Usa JPG, PNG o WEBP.');
    }

    // Path en el bucket
    $safeName   = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'imagen';
    $objectPath = sprintf('portadas/%s/%s/%s_%s', date('Y'), date('m'), uniqid('dev_', true), $safeName);

    // Token público
    $token = bin2hex(random_bytes(16));

    // Subir
    $bucket->upload(
        fopen($tmp, 'r'),
        [
            'name' => $objectPath,
            'metadata' => [
                'contentType'  => $mime,
                'cacheControl' => 'public, max-age=31536000, immutable',
                'metadata'     => ['firebaseStorageDownloadTokens' => $token],
            ],
        ]
    );
    up_log('Subido: '.$objectPath.' MIME:'.$mime.' bytes:'.$size);

    // URL pública
    $bucketName = $bucket->name();
    $publicUrl  = 'https://firebasestorage.googleapis.com/v0/b/'.$bucketName.'/o/'.rawurlencode($objectPath).'?alt=media&token='.$token;

    echo json_encode(['ok'=>true,'url'=>$publicUrl,'path'=>$objectPath], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // LOG y respuesta clara
    $msg = $e->getMessage();
    if (method_exists($e, 'getTraceAsString')) {
        up_log('ERROR: '.$msg."\n".$e->getTraceAsString());
    } else {
        up_log('ERROR: '.$msg);
    }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_SLASHES);
}
