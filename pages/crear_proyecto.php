<?php
declare(strict_types=1);

/**
 * crear_proyecto.php
 * - Recibe: POST (NombreDesarrollo, Direccion, FotoDesarrollo[file])
 * - Usa Kreait (Admin SDK PHP) para subir la imagen a Firebase Storage
 *   y guardar el registro en Realtime Database.
 * - Devuelve JSON puro.
 */

@ini_set('display_errors', '0');
@ini_set('zlib.output_compression', '0');
header_remove();
header('Content-Type: application/json; charset=utf-8');
ob_start();

try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_clean(); echo json_encode(['status'=>'error','message'=>'Método no permitido']); exit;
    }

    // Límite php.ini
    if (empty($_POST) && empty($_FILES) && ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)) {
        http_response_code(413);
        ob_clean(); echo json_encode(['status'=>'error','message'=>'El archivo excede los límites del servidor (upload_max_filesize/post_max_size)']); exit;
    }

    // Id del empresario (desde sesión preferente)
    $idEmpresario = $_SESSION['idDesarrollo'] ?? ($_POST['idEmpresario'] ?? null);
    if (!$idEmpresario) {
        http_response_code(400);
        ob_clean(); echo json_encode(['status'=>'error','message'=>'Falta id del empresario en la sesión']); exit;
    }

    $nombre    = trim($_POST['NombreDesarrollo'] ?? '');
    $direccion = trim($_POST['Direccion'] ?? '');
    if ($nombre === '' || $direccion === '') {
        http_response_code(422);
        ob_clean(); echo json_encode(['status'=>'error','message'=>'Nombre y Dirección son obligatorios']); exit;
    }

    if (!isset($_FILES['FotoDesarrollo']) || $_FILES['FotoDesarrollo']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['FotoDesarrollo']['error'] ?? 'sin archivo';
        http_response_code(422);
        ob_clean(); echo json_encode(['status'=>'error','message'=>"Falta la imagen o hubo error al subirla (code: {$err})"]); exit;
    }

    $tmpPath = $_FILES['FotoDesarrollo']['tmp_name'];
    $mime    = @mime_content_type($tmpPath) ?: ($_FILES['FotoDesarrollo']['type'] ?? 'application/octet-stream');
    if (strpos($mime, 'image/') !== 0) {
        http_response_code(422);
        ob_clean(); echo json_encode(['status'=>'error','message'=>'El archivo no es una imagen válida']); exit;
    }

    // ==== Firebase Admin (Kreait) ====
    require_once __DIR__ . '/../config/firebase_init.php'; // Debe definir $database y $storage (Factory->createDatabase/createStorage)

    // Verifica y usa el bucket REAL:
    $BUCKET_NAME = 'dmgvent.firebasestorage.app'; // <- tu bucket exacto
    /** @var \Google\Cloud\Storage\StorageClient $storage */
    $bucket = $storage->getBucket($BUCKET_NAME);
    // Forzar error si no existe / sin permisos
    $bucket->info();

    $basePath = "projects/proj_8HNCM2DFob/data/DesarrollosEmpresarios/{$idEmpresario}/Desarrollos";

    // 1) Crear placeholder para generar ID
    $ref   = $database->getReference($basePath)->push(['createdAt' => time()]);
    $newId = $ref->getKey();
    if (!$newId) {
        throw new RuntimeException('No se pudo generar el ID del nuevo registro.');
    }

    // 2) Subir imagen a Storage con token de descarga
    $safeName   = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $_FILES['FotoDesarrollo']['name']);
    $safeName   = strtolower($safeName);
    $objectPath = "desarrollos/{$idEmpresario}/{$newId}/{$safeName}";

    // Token de descarga para URL pública
    $token = bin2hex(random_bytes(16));

    $bucket->upload(
        fopen($tmpPath, 'r'),
        [
            'name' => $objectPath,
            'metadata' => [
                'contentType' => $mime,
                'metadata'    => [
                    // NECESARIO para tener downloadURL estilo Firebase
                    'firebaseStorageDownloadTokens' => $token,
                ],
            ],
        ]
    );

    // 3) Construir URL pública
    $publicUrl  = "https://firebasestorage.googleapis.com/v0/b/{$BUCKET_NAME}/o/"
                . rawurlencode($objectPath)
                . "?alt=media&token={$token}";

    // 4) Guardar datos finales en RTDB (sobrescribe placeholder)
    $data = [
        'idDesarrollo'     => $newId,
        'NombreDesarrollo' => $nombre,
        'Direccion'        => $direccion,
        'FotoDesarrollo'   => $publicUrl,
        'updatedAt'        => time(),
    ];
    $database->getReference("{$basePath}/{$newId}")->set($data);

    // Respuesta JSON limpia
    http_response_code(200);
    ob_clean();
    echo json_encode(['status'=>'ok','id'=>$newId,'url'=>$publicUrl], JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500);
    ob_clean();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    exit;
}
