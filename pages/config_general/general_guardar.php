<?php
// pages/config_general/general_guardar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase_init.php';

const ROOT_PATH = 'projects/proj_8HNCM2DFob/data';

/* ---------------- Helpers ---------------- */

function get_bucket($storage) {
  // Ajusta si tu bucket real es otro
  $BUCKET_NAME = 'dmgvent.firebasestorage.app';
  try {
    $b = $storage->getBucket($BUCKET_NAME);
    $b->info();
    return $b;
  } catch (Throwable $e) {
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

/** Sube archivo a Storage con ACL pública y devuelve URL pública */
function upload_public_url($storage, string $destPath, string $tmpPath, string $contentType): string {
  $bucket = get_bucket($storage);
  $bucket->upload(
    file_get_contents($tmpPath),
    ['name' => $destPath, 'predefinedAcl' => 'publicRead', 'metadata' => ['contentType' => $contentType ?: 'application/octet-stream']]
  );
  $bname = method_exists($bucket,'name') ? $bucket->name() : $bucket->name;
  return "https://firebasestorage.googleapis.com/v0/b/{$bname}/o/" . rawurlencode($destPath) . "?alt=media";
}

/* ---------------- Main ---------------- */

try {
  if (session_status() === PHP_SESSION_NONE) session_start();

  // Normalizar POST (keys → minúsculas)
  $IN = [];
  foreach ($_POST as $k => $v) $IN[strtolower(trim((string)$k))] = is_string($v) ? trim($v) : $v;

  // id del desarrollo
  $id = $IN['iddesarrollo'] ?? $IN['id'] ?? ($_SESSION['idDesarrollo'] ?? '');
  $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$id);
  if ($id === '') throw new RuntimeException('Falta id del desarrollo.');

  $get = function(array $aliases) use ($IN) {
    foreach ($aliases as $a) { $k = strtolower(trim($a)); if (array_key_exists($k, $IN)) return $IN[$k]; }
    return null;
  };

  /* ------- Campos Generales a actualizar en DesarrollosGenerales ------- */
  $updatesGen = [];

  // Nombre(s)
  if (($v = $get(['nombredashboard'])) !== null)                $updatesGen['NombreDashboard']   = (string)$v;
  if (($v = $get(['nombredesarrollo','nombre'])) !== null)      $updatesGen['NombreDesarrollo']  = (string)$v;

  // Número de expediente (nuevo)
  if (($v = $get(['numeroexpediente','expediente'])) !== null)  $updatesGen['NumeroExpediente']  = (string)$v;

  // Datos base
  if (($v = $get(['direccion']))   !== null)                    $updatesGen['Direccion']         = (string)$v;
  if (($v = $get(['email','correo','gmail'])) !== null)         $updatesGen['Email']             = (string)$v;
  if (($v = $get(['responsable','encargado','nombreresponsable'])) !== null) $updatesGen['Responsable'] = (string)$v;
  if (($v = $get(['telefono','tel','celular','phone'])) !== null)            $updatesGen['Telefono']    = (string)$v;

  // Lat / Lng
  $lat = $get(['latitude','latitud','lat']);
  $lng = $get(['longitude','longitud','lng']);
  if ($lat !== null) $updatesGen['Latitude']  = to_number_or_null($lat) ?? (string)$lat;
  if ($lng !== null) $updatesGen['Longitude'] = to_number_or_null($lng) ?? (string)$lng;

  /* ------- Portada: URL o Archivo (igual que ya tenías) ------- */
  $portadaUrlPublica = '';
  $portadaUrlEscrita = $get(['portada']);
  if (!empty($_FILES['PortadaFile']['tmp_name']) && is_uploaded_file($_FILES['PortadaFile']['tmp_name'])) {
    $tmp  = $_FILES['PortadaFile']['tmp_name'];
    $name = basename($_FILES['PortadaFile']['name']);
    $dest = "portadas/$id/" . date('Ymd_His') . "_$name";
    $portadaUrlPublica = upload_public_url($storage, $dest, $tmp, @mime_content_type($tmp) ?: 'image/jpeg');
    $updatesGen['Portada'] = $portadaUrlPublica;
  } elseif ($portadaUrlEscrita !== null) {
    // permitir limpiar si viene vacío
    $updatesGen['Portada'] = ($portadaUrlEscrita === '') ? null : (string)$portadaUrlEscrita;
  }

  /* ------- PLANO GENERAL: URL y/o Archivo PDF (NUEVO) ------- */
  $planoUrlPublica = '';
  $planoUrlEscrita = $get(['planogeneral','plano']); // aceptamos ambas claves

  if (!empty($_FILES['PlanoGeneralFile']['tmp_name']) && is_uploaded_file($_FILES['PlanoGeneralFile']['tmp_name'])) {
    $tmp  = $_FILES['PlanoGeneralFile']['tmp_name'];
    $name = basename($_FILES['PlanoGeneralFile']['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new RuntimeException('El plano debe ser PDF.');
    $dest = "planos/$id/" . date('Ymd_His') . "_$name";
    $planoUrlPublica = upload_public_url($storage, $dest, $tmp, 'application/pdf');
    // guardamos con la clave usada por tu tabla
    $updatesGen['PlanoGeneral'] = $planoUrlPublica;
    // y por compatibilidad también 'Plano'
    $updatesGen['Plano'] = $planoUrlPublica;
  } elseif ($planoUrlEscrita !== null) {
    // si mandan el campo vacío => borrar
    $val = ($planoUrlEscrita === '') ? null : (string)$planoUrlEscrita;
    $updatesGen['PlanoGeneral'] = $val;
    $updatesGen['Plano']        = $val;
  }

  if (!$updatesGen) throw new RuntimeException('No hay campos para actualizar.');

  /* ------- Escribir en DesarrollosGenerales/{id} ------- */
  $pathGen = ROOT_PATH . "/DesarrollosGenerales/$id";
  $refGen  = $database->getReference($pathGen);
  $refGen->update($updatesGen);
  $afterGen = $refGen->getSnapshot()->getValue();

  /* ------- Sincronizar en DesarrollosEmpresarios/{empId}/Desarrollos/{id} ------- */
  $empId = find_empresario_for($id, $database);
  $pathEmp = '';
  $afterEmp = null;
  if ($empId) {
    $pathEmp = ROOT_PATH . "/DesarrollosEmpresarios/$empId/Desarrollos/$id";
    $refEmp  = $database->getReference($pathEmp);

    // Copiamos payload con pequeños ajustes de naming
    $updatesEmp = $updatesGen;
    if (array_key_exists('Portada', $updatesEmp)) {
      $updatesEmp['FotoDesarrollo'] = $updatesEmp['Portada'];
      unset($updatesEmp['Portada']);
    }
    // En Empresarios guardamos PlanoGeneral y Plano por compatibilidad igual
    $refEmp->update($updatesEmp);
    $afterEmp = $refEmp->getSnapshot()->getValue();
  }

  /* ------- (Opcional) Reflejar Telefono/Responsable en Perfiles/{id} ------- */
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
    'paths'         => ['generales' => $pathGen, 'empresarios' => $pathEmp],
    'updated'       => ['generales' => $updatesGen],
    'after'         => ['generales' => $afterGen, 'empresarios' => $afterEmp],
    // Para que el front lo pinte sin depender de dónde vino
    'portadaUrl'    => $updatesGen['Portada'] ?? ($updatesEmp['FotoDesarrollo'] ?? ''),
    'planoUrl'      => $planoUrlPublica ?: ($updatesGen['PlanoGeneral'] ?? ''),
    'urlPlano'      => $planoUrlPublica ?: ($updatesGen['PlanoGeneral'] ?? ''),
    'mirroredTo'    => $mirrored
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
