<?php
// pages/generales/save.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/firebase_init.php';
header('Content-Type: application/json; charset=utf-8');

$ROOT = 'projects/proj_8HNCM2DFob/data';

/** Encuentra el idEmpresario que contiene el desarrollo */
function find_empresario_for(string $idDes, $db, string $root): ?string {
  $snap = $db->getReference("$root/DesarrollosEmpresarios")->getSnapshot();
  $all  = $snap->getValue() ?: [];
  if (is_array($all)) {
    foreach ($all as $empId => $emp) {
      if (isset($emp['Desarrollos'][$idDes])) return (string)$empId;
    }
  }
  return null;
}

/** Devuelve el bucket; fuerza el nombre que nos indicaste */
function get_bucket($storage) {
  // OJO: muchos proyectos usan <project-id>.appspot.com; mantenemos fallback.
  $BUCKET_NAME = 'dmgvent.firebasestorage.app';
  try {
    $b = $storage->getBucket($BUCKET_NAME);
    $b->info();
    return $b;
  } catch (\Throwable $e) {
    $b = $storage->getBucket();
    $b->info();
    return $b;
  }
}

/** Sube con token y devuelve URL pública */
function upload_with_token($bucket, string $tmpPath, string $destPath, string $contentType): string {
  $token = bin2hex(random_bytes(16));
  $bucket->upload(
    fopen($tmpPath, 'r'),
    [
      'name' => $destPath,
      'metadata' => [
        'contentType' => $contentType ?: 'application/octet-stream',
        'metadata'    => [
          'firebaseStorageDownloadTokens' => $token,
        ],
      ],
    ]
  );
  $bname = method_exists($bucket, 'name') ? $bucket->name() : $bucket->name;
  return "https://firebasestorage.googleapis.com/v0/b/{$bname}/o/" . rawurlencode($destPath) . "?alt=media&token={$token}";
}

/** Casteo suave para números (acepta coma decimal) */
function to_number_or_string($val) {
  if ($val === null) return '';
  if (is_string($val)) {
    $s = str_replace(',', '.', trim($val));
    if ($s === '') return '';
    if (is_numeric($s)) return $s + 0;
    return $val; // deja string si no es numérico
  }
  if (is_int($val) || is_float($val)) return $val + 0;
  return (string)$val;
}

try {
  $ctype = $_SERVER['CONTENT_TYPE'] ?? '';

  // ===================== ACCIONES (JSON) PARA CUENTAS =====================
  if (stripos($ctype, 'application/json') !== false) {
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string)($body['action'] ?? '');
    $id     = preg_replace('/[^A-Za-z0-9_\-]/','', (string)($body['id'] ?? ''));

    if ($id === '') throw new RuntimeException('Falta id.');

    if ($action === 'acct_add') {
      $d   = (array)($body['data'] ?? []);
      $row = [
        'Banco'        => (string)($d['banco'] ?? ''),
        'Beneficiario' => (string)($d['beneficiario'] ?? ''),
        'CLABE'        => (string)($d['clabe'] ?? ''),
        'idCuenta'     => (string)($d['idCuenta'] ?? '')
      ];
      $ref = $database->getReference("$ROOT/DesarrollosGenerales/$id/Cuentas")->push($row);
      echo json_encode(['ok'=>true, 'rid'=>$ref->getKey()], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($action === 'acct_edit') {
      $rid = preg_replace('/[^A-Za-z0-9_\-]/','', (string)($body['rid'] ?? ''));
      if ($rid === '') throw new RuntimeException('Falta rid.');
      $d = (array)($body['data'] ?? []);
      $row = array_filter([
        'Banco'        => array_key_exists('banco',        $d) ? (string)$d['banco']        : null,
        'Beneficiario' => array_key_exists('beneficiario', $d) ? (string)$d['beneficiario'] : null,
        'CLABE'        => array_key_exists('clabe',        $d) ? (string)$d['clabe']        : null,
        'idCuenta'     => array_key_exists('idCuenta',     $d) ? (string)$d['idCuenta']     : null,
      ], fn($v)=> $v !== null);
      $database->getReference("$ROOT/DesarrollosGenerales/$id/Cuentas/$rid")->update($row);
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($action === 'acct_del') {
      $rid = preg_replace('/[^A-Za-z0-9_\-]/','', (string)($body['rid'] ?? ''));
      if ($rid==='') throw new RuntimeException('Falta rid.');
      $database->getReference("$ROOT/DesarrollosGenerales/$id/Cuentas/$rid")->remove();
      echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
      exit;
    }

    throw new RuntimeException('Acción no soportada.');
  }

  // ===================== GUARDAR GENERALES (form/multipart) =====================
  $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_POST['id'] ?? ''));
  if ($id === '') throw new RuntimeException('Falta id.');

  $empId = find_empresario_for($id, $database, $ROOT);
  if (!$empId) throw new RuntimeException('No se encontró el empresario dueño de este desarrollo.');

  // Campos (normalizados)
  $nombreDesarrollo = (string)($_POST['NombreDesarrollo'] ?? '');
  $responsable      = (string)($_POST['Responsable']      ?? '');
  $direccion        = (string)($_POST['Direccion']        ?? '');
  $telefono         = (string)($_POST['Telefono']         ?? '');
  $email            = (string)($_POST['Email']            ?? '');
  $latitude         = to_number_or_string($_POST['Latitude']  ?? '');
  $longitude        = to_number_or_string($_POST['Longitude'] ?? '');

  $data = [
    'NombreDesarrollo' => $nombreDesarrollo,
    'Responsable'      => $responsable,
    'Direccion'        => $direccion,
    'Telefono'         => $telefono,
    'Email'            => $email,
    'Latitude'         => $latitude,
    'Longitude'        => $longitude,
  ];

  $urlEscrita = (string)($_POST['Portada'] ?? '');
  $urlSubida  = '';

  // ¿Viene archivo? -> sube a desarrollos/<empresario>/<desarrollo>/
  if (!empty($_FILES['PortadaFile']['tmp_name']) && is_uploaded_file($_FILES['PortadaFile']['tmp_name'])) {
    $tmp  = $_FILES['PortadaFile']['tmp_name'];
    $ext  = strtolower(pathinfo($_FILES['PortadaFile']['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $mime = @mime_content_type($tmp) ?: ($_FILES['PortadaFile']['type'] ?? 'application/octet-stream');

    $bucket = get_bucket($storage);
    $object = "desarrollos/{$empId}/{$id}/portada_" . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    $urlSubida = upload_with_token($bucket, $tmp, $object, $mime);
  }

  $fotoFinal = $urlSubida ?: $urlEscrita;
  if ($fotoFinal !== '') {
    // Así se llama en el nodo Empresarios
    $data['FotoDesarrollo'] = $fotoFinal;
  }

  // 1) Guardar en DesarrollosEmpresarios/{empId}/Desarrollos/{id}
  $refEmp = "$ROOT/DesarrollosEmpresarios/$empId/Desarrollos/$id";
  $database->getReference($refEmp)->update($data);

  // 2) Sincronizar SIEMPRE a DesarrollosGenerales/{id} (todos los campos usados por la tabla)
  $sync = [
    'NombreDesarrollo' => $nombreDesarrollo,
    'Direccion'        => $direccion,
    'Email'            => $email,
    'Responsable'      => $responsable,
    'Telefono'         => $telefono,
    'Latitude'         => $latitude,
    'Longitude'        => $longitude,
  ];
  if ($fotoFinal) {
    // En Generales normalmente se llama Portada
    $sync['Portada'] = $fotoFinal;
  }
  $genRef = "$ROOT/DesarrollosGenerales/$id";
  $database->getReference($genRef)->update($sync);

  // 3) (Opcional) reflejar Responsable/Telefono en DesarrollosPerfiles/{id} si existe
  $perfRef = "$ROOT/DesarrollosPerfiles/$id";
  $snapPerf = $database->getReference($perfRef)->getSnapshot();
  if ($snapPerf->exists()) {
    $mirror = [];
    if ($responsable !== '') $mirror['Responsable'] = $responsable;
    if ($telefono !== '')    $mirror['Telefono']    = $telefono;
    if ($mirror) $database->getReference($perfRef)->update($mirror);
  }

  echo json_encode(['ok'=>true,'urlPortada'=>$fotoFinal], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
