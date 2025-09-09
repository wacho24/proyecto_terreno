<?php
// pages/config_general/general_guardar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase_init.php';

const ROOT_PATH = 'projects/proj_8HNCM2DFob/data'; // ajusta si tu projectId cambia

/* -------- Helpers -------- */

function get_bucket($storage) {
  // ⚠️ Cambia si tu bucket real es otro (revisa en Firebase Storage)
  $BUCKET_NAME = 'dmgvent.firebasestorage.app';
  try {
    $b = $storage->getBucket($BUCKET_NAME);
    $b->info();
    return $b;
  } catch (Throwable $e) {
    // Respaldo: bucket por defecto del SDK
    $b = $storage->getBucket();
    $b->info();
    return $b;
  }
}

function to_number_or_null($x) {
  if ($x === null) return null;
  if (is_string($x)) {
    $s = str_replace(',', '.', trim($x));
    if ($s === '') return null;
    return is_numeric($s) ? $s + 0 : null;
  }
  if (is_int($x) || is_float($x)) return $x + 0;
  return null;
}

/** Encuentra el id del Empresario dueño del desarrollo */
function find_empresario_for(string $idDesarrollo, $db): ?string {
  $snap = $db->getReference(ROOT_PATH . '/DesarrollosEmpresarios')->getSnapshot();
  $all  = $snap->getValue() ?: [];
  if (!is_array($all)) return null;
  foreach ($all as $empId => $emp) {
    if (isset($emp['Desarrollos'][$idDesarrollo])) return (string)$empId;
  }
  return null;
}

/* -------- Main -------- */

try {
  if (session_status() === PHP_SESSION_NONE) session_start();

  // Normaliza POST (keys en minúsculas)
  $IN = [];
  foreach ($_POST as $k => $v) $IN[strtolower(trim((string)$k))] = is_string($v) ? trim($v) : $v;

  // ID de desarrollo (POST tiene prioridad)
  $id = $IN['iddesarrollo'] ?? $IN['id'] ?? ($_SESSION['idDesarrollo'] ?? '');
  $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$id);
  if ($id === '') throw new RuntimeException('Falta id del desarrollo.');

  $get = function(array $aliases) use ($IN) {
    foreach ($aliases as $a) { $k = strtolower(trim($a)); if (array_key_exists($k, $IN)) return $IN[$k]; }
    return null;
  };

  // Construir payload canónico para DesarrollosGenerales
  $updatesGen = [];

  // Nombres
  if (($v = $get(['nombredashboard'])) !== null)  $updatesGen['NombreDashboard']  = (string)$v;
  if (($v = $get(['nombredesarrollo','nombre'])) !== null) $updatesGen['NombreDesarrollo'] = (string)$v;

  // Datos generales
  if (($v = $get(['direccion']))   !== null) $updatesGen['Direccion']   = (string)$v;
  if (($v = $get(['email','correo','gmail'])) !== null) $updatesGen['Email'] = (string)$v;
  if (($v = $get(['responsable','encargado','nombreresponsable'])) !== null) $updatesGen['Responsable'] = (string)$v;
  if (($v = $get(['telefono','tel','celular','phone'])) !== null) $updatesGen['Telefono'] = (string)$v;

  // Lat/Lng (acepta español/inglés y coma decimal)
  $lat = $get(['latitude','latitud','lat']);
  $lng = $get(['longitude','longitud','lng']);
  if ($lat !== null) $updatesGen['Latitude']  = to_number_or_null($lat) ?? (string)$lat;
  if ($lng !== null) $updatesGen['Longitude'] = to_number_or_null($lng) ?? (string)$lng;

  // Portada: URL directa o archivo
  $portadaUrlPublica = '';
  $portadaUrlEscrita = $get(['portada']); // si mandas una URL ya existente
  if (!empty($_FILES['PortadaFile']['tmp_name']) && is_uploaded_file($_FILES['PortadaFile']['tmp_name'])) {
    $tmp  = $_FILES['PortadaFile']['tmp_name'];
    $name = basename($_FILES['PortadaFile']['name']);
    $ext  = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
      throw new RuntimeException('Formato de imagen no permitido.');
    }
    $destPath = "portadas/$id/" . date('Ymd_His') . "_$name";
    $bucket   = get_bucket($storage);
    // sube público (simple)
    $bucket->upload(file_get_contents($tmp), ['name' => $destPath, 'predefinedAcl' => 'publicRead']);
    $bname = method_exists($bucket,'name') ? $bucket->name() : $bucket->name;
    $portadaUrlPublica = "https://firebasestorage.googleapis.com/v0/b/{$bname}/o/" . rawurlencode($destPath) . "?alt=media";
    $updatesGen['Portada'] = $portadaUrlPublica; // en Generales usamos 'Portada'
  } elseif (!empty($portadaUrlEscrita)) {
    $updatesGen['Portada'] = (string)$portadaUrlEscrita;
  }

  if (!$updatesGen) throw new RuntimeException('No hay campos para actualizar.');

  // 1) Escribir en DesarrollosGenerales/{id}
  $pathGen = ROOT_PATH . "/DesarrollosGenerales/$id";
  $refGen  = $database->getReference($pathGen);
  $refGen->update($updatesGen);
  $afterGen = $refGen->getSnapshot()->getValue();

  // 2) Sincronizar a DesarrollosEmpresarios/{empId}/Desarrollos/{id}
  $empId = find_empresario_for($id, $database);
  $pathEmp = '';
  $afterEmp = null;
  if ($empId) {
    $pathEmp = ROOT_PATH . "/DesarrollosEmpresarios/$empId/Desarrollos/$id";
    $refEmp  = $database->getReference($pathEmp);

    // Tomamos el mismo payload pero ajustamos clave de imagen:
    $updatesEmp = $updatesGen;
    if (isset($updatesEmp['Portada'])) {
      $updatesEmp['FotoDesarrollo'] = $updatesEmp['Portada'];
      unset($updatesEmp['Portada']);
    }
    $refEmp->update($updatesEmp);
    $afterEmp = $refEmp->getSnapshot()->getValue();
  }

  // 3) (Opcional) Reflejar Telefono/Responsable en DesarrollosPerfiles/{id} si existe
  $refPerf = $database->getReference(ROOT_PATH . "/DesarrollosPerfiles/$id");
  $snapPerf = $refPerf->getSnapshot();
  $mirrored = [];
  if ($snapPerf->exists()) {
    $mirror = [];
    if (array_key_exists('Telefono',   $updatesGen)) $mirror['Telefono']   = $updatesGen['Telefono'];
    if (array_key_exists('Responsable',$updatesGen)) $mirror['Responsable'] = $updatesGen['Responsable'];
    if ($mirror) { $refPerf->update($mirror); $mirrored = array_keys($mirror); }
  }

  echo json_encode([
    'ok'            => true,
    'received_keys' => array_keys($_POST),
    'paths'         => ['generales' => $pathGen, 'empresarios' => $pathEmp],
    'updated'       => ['generales' => $updatesGen],
    'after'         => ['generales' => $afterGen, 'empresarios' => $afterEmp],
    'portadaUrl'    => $updatesGen['Portada'] ?? ($updatesEmp['FotoDesarrollo'] ?? ''),
    'mirroredTo'    => $mirrored
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
