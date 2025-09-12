<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Google\Cloud\Storage\Bucket;

/** Credenciales */
$serviceAccount = __DIR__ . '/firebase_credentials.json';
$databaseUrl    = 'https://dmgvent-default-rtdb.firebaseio.com';

$factory = (new Factory())
    ->withServiceAccount($serviceAccount)
    ->withDatabaseUri($databaseUrl);

$auth     = $factory->createAuth();
$database = $factory->createDatabase();
$storage  = $factory->createStorage();

/** Bucket automático basado en project_id => project-id.appspot.com */
function fb_bucket(): Bucket {
    global $storage, $serviceAccount;
    $json = json_decode(file_get_contents($serviceAccount), true);
    if (!isset($json['project_id'])) {
        throw new RuntimeException('project_id no encontrado en firebase_credentials.json');
    }
    $bucketName ='dmgvent.firebasestorage.app';
    return $storage->getBucket($bucketName);
}
// No cierres con "?>"
