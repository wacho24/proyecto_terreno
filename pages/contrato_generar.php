<?php
// pages/contrato_generar.php
declare(strict_types=1);

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/firebase_init.php';

/* =================== Salida JSON limpia =================== */
@ini_set('display_errors', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }
function jexit(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

/* =================== Helpers =================== */
const ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

function safe_mkdir(string $dir): string {
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  return realpath($dir) ?: $dir;
}
function money_mx(float $n): string { return '$ '.number_format($n, 2, '.', ','); }
function dash($v): string { $s = trim((string)($v ?? '')); return $s === '' ? '—' : $s; }
function norm_label(string $s): string {
  $x = mb_strtolower(trim($s), 'UTF-8');
  $x = preg_replace('/\s+/', ' ', $x);
  return preg_replace('/\s*[—–-]\s*/u', ' - ', $x);
}

/** Cuentas CTA1..CTA3 */
function load_cuentas($db, string $idDes): array {
  $snap = $db->getReference(ROOT_PREFIX."/DesarrollosGenerales/$idDes/Cuentas")->getSnapshot();
  $arr  = $snap->getValue() ?: [];
  $out  = [];
  if (is_array($arr)) {
    foreach ($arr as $r) {
      $out[] = [
        'Banco'        => (string)($r['Banco']        ?? ''),
        'Beneficiario' => (string)($r['Beneficiario'] ?? ''),
        'CLABE'        => (string)($r['CLABE']        ?? ''),
        'idCuenta'     => (string)($r['idCuenta']     ?? ''),
      ];
    }
  }
  return $out;
}

/** Mapas de Lotes (por id y por etiqueta normalizada) y nombres de manzana */
function load_lotes_map($db, string $idDes): array {
  $manz = [];
  try {
    $manzRaw = $db->getReference(ROOT_PREFIX."/DesarrollosGenerales/$idDes/Manzanas")->getSnapshot()->getValue() ?: [];
    foreach ($manzRaw as $mid => $m) $manz[$mid] = (string)($m['NombreManzana'] ?? $mid);
  } catch (\Throwable $e) {}

  $byId = []; $byLabel = [];
  try {
    $rows = $db->getReference(ROOT_PREFIX."/DesarrollosGenerales/$idDes/Lotes")->getSnapshot()->getValue() ?: [];
    foreach ($rows as $lid => $l) {
      $mf = (float)($l['MedidaFrente']    ?? $l['MedidaFrontal']   ?? $l['MF']  ?? 0);
      $md = (float)($l['MedidaDerecho']   ?? $l['MedidaDerecha']   ?? $l['MCD'] ?? $l['MD'] ?? 0);
      $mi = (float)($l['MedidaIzquierdo'] ?? $l['MedidaIzquierda'] ?? $l['MI']  ?? 0);
      $mp = (float)($l['MedidaFondo']     ?? $l['MedidaPosterior'] ?? $l['MP']  ?? 0);
      $area = (float)($l['Area'] ?? (($md+$mi)*($mf+$mp)));

      $item = [
        'id'         => (string)$lid,
        'manzanaId'  => (string)($l['idManzana'] ?? $l['ManzanaId'] ?? ''),
        'manzana'    => '',
        'descripcion'=> (string)($l['NombreLote'] ?? $l['Descripcion'] ?? $lid),
        'pventa'     => (float)($l['Precio'] ?? $l['PrecioVenta'] ?? 0),
        'nota'       => (string)($l['Nota'] ?? ''),
        'mf'=>$mf,'md'=>$md,'mi'=>$mi,'mp'=>$mp,'area'=>$area,
      ];
      $item['manzana'] = $manz[$item['manzanaId']] ?? '';
      $byId[$lid] = $item;

      foreach (array_filter([
        $item['descripcion'],
        $item['descripcion'].' - '.$item['manzana'],
        $item['manzana'].' - '.$item['descripcion'],
      ]) as $lb) {
        $byLabel[norm_label($lb)] = $lid;
      }
    }
  } catch (\Throwable $e) {}

  return ['byId'=>$byId, 'byLabel'=>$byLabel];
}

/** Cliente desde rutas candidatas; incluye UbicacionLibre */
function load_cliente($db, string $idDes, string $clienteId): array {
  $desInfo  = $db->getReference(ROOT_PREFIX."/DesarrollosGenerales/$idDes")->getSnapshot()->getValue() ?: [];
  $desIdEmp = (string)($desInfo['idDesarrollo'] ?? '');

  $candidates = [
    ROOT_PREFIX."/DesarrollosClientes/$idDes/Clientes/$clienteId",
    ROOT_PREFIX."/DesarrollosGenerales/$idDes/Clientes/$clienteId",
  ];
  if ($desIdEmp !== '') {
    $candidates[] = ROOT_PREFIX."/Empresarios/$desIdEmp/Clientes/$clienteId";
  }

  foreach ($candidates as $p) {
    try {
      $v = $db->getReference($p)->getSnapshot()->getValue();
      if (is_array($v) && $v) {
        return [
          'Nombre'         => (string)($v['Nombre']         ?? ''),
          'Curp'           => (string)($v['Curp']           ?? ''),
          'Telefono'       => (string)($v['Telefono']       ?? ''),
          'Email'          => (string)($v['Email']          ?? ''),
          'Pais'           => (string)($v['Pais']           ?? ''),
          'Estado'         => (string)($v['Estado']         ?? ''),
          'Municipio'      => (string)($v['Municipio']      ?? ''),
          'Localidad'      => (string)($v['Localidad']      ?? ''),
          'Calle'          => (string)($v['Calle']          ?? ''),
          'CodigoPostal'   => (string)($v['CodigoPostal']   ?? ''),
          'UbicacionLibre' => (string)($v['UbicacionLibre'] ?? ''),
        ];
      }
    } catch (\Throwable $e) {}
  }
  return [];
}

/* =================== MAIN =================== */
try {
  // 1) Entrada
  $raw = file_get_contents('php://input');
  if ($raw === false) jexit(['ok'=>false,'error'=>'No se pudo leer la entrada.'], 400);
  $in  = json_decode($raw, true);
  if (!is_array($in)) jexit(['ok'=>false,'error'=>'Payload no es JSON.'], 400);

  $idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($in['idDesarrollo'] ?? ''));
  if ($idDesarrollo === '') jexit(['ok'=>false,'error'=>'Falta idDesarrollo.'], 400);

  $venta   = is_array($in['venta'] ?? null) ? $in['venta'] : [];
  $formato = strtolower((string)($in['formato'] ?? 'docx'));
  if (!in_array($formato, ['docx','pdf'], true)) $formato = 'docx';

  $clienteId    = (string)($venta['cliente'] ?? $in['cliente'] ?? '');
  $clienteLabel = (string)($in['clienteLabel'] ?? $venta['clienteLabel'] ?? '');

  // 2) Proyecto
  $gen     = $database->getReference(ROOT_PREFIX."/DesarrollosGenerales/$idDesarrollo")->getSnapshot()->getValue() ?: [];
  $proyNombre = dash($gen['NombreDesarrollo'] ?? $gen['NombreDashboard'] ?? '');
  $proyDir    = dash($gen['Direccion'] ?? '');
  $proyResp   = dash($gen['Responsable'] ?? '');
  $proyTel    = dash($gen['Telefono'] ?? '');
  $proyEmail  = dash($gen['Email'] ?? '');

  $cuentas    = load_cuentas($database, $idDesarrollo);

  // 3) Lotes
  $maps   = load_lotes_map($database, $idDesarrollo);
  $byId   = $maps['byId'];  $byLbl = $maps['byLabel'];

  $rows = []; $total = 0.0;
  if (isset($venta['lotes']) && is_array($venta['lotes']) && $venta['lotes']) {
    foreach ($venta['lotes'] as $L) {
      $id = (string)($L['id'] ?? '');
      $lbl = (string)($L['label'] ?? $id);
      $precio = (float)($L['precio'] ?? 0);

      $lotDb = $id && isset($byId[$id]) ? $byId[$id] : (isset($byLbl[norm_label($lbl)]) ? $byId[$byLbl[norm_label($lbl)]] : null);
      $manzana = $lotDb['manzana'] ?? '';
      $medidas = $lotDb ? ("F: {$lotDb['mf']}  D: {$lotDb['md']}  I: {$lotDb['mi']}  P: {$lotDb['mp']} (Área: {$lotDb['area']} m²)") : '';
      $desc    = $lotDb['nota'] ?? '';
      if ($precio <= 0 && $lotDb) $precio = (float)$lotDb['pventa'];

      $rows[] = [
        'LOTE_NO'      => $lbl,
        'LOTE_MANZANA' => $manzana,
        'LOTE_MEDIDAS' => $medidas,
        'LOTE_DESC'    => $desc,
        'LOTE_PRECIO'  => money_mx(max(0,$precio)),
      ];
      $total += max(0,$precio);
    }
  } elseif (!empty($venta['lote']) && is_string($venta['lote'])) {
    foreach (array_filter(array_map('trim', explode(',', $venta['lote']))) as $lbl) {
      $lid   = $byLbl[norm_label($lbl)] ?? '';
      $lotDb = $lid && isset($byId[$lid]) ? $byId[$lid] : null;
      $precio= (float)($lotDb['pventa'] ?? 0);
      $rows[] = [
        'LOTE_NO'      => $lbl,
        'LOTE_MANZANA' => $lotDb['manzana'] ?? '',
        'LOTE_MEDIDAS' => $lotDb ? ("F: {$lotDb['mf']}  D: {$lotDb['md']}  I: {$lotDb['mi']}  P: {$lotDb['mp']} (Área: {$lotDb['area']} m²)") : '',
        'LOTE_DESC'    => $lotDb['nota'] ?? '',
        'LOTE_PRECIO'  => money_mx($precio),
      ];
      $total += max(0,$precio);
    }
  }

  // 4) Cliente y asesor
  $cli = $clienteId !== '' ? load_cliente($database, $idDesarrollo, $clienteId) : [];
  $clienteNombre = $cli['Nombre'] ?: ($clienteLabel ?: '—');
  $clienteCurp   = $cli['Curp'] ?? '';
  $clienteTel    = $cli['Telefono'] ?? '';
  $clienteEmail  = $cli['Email'] ?? '';

  // Domicilio: primero UbicacionLibre; si no, construye con campos
  $clienteDom = trim((string)($cli['UbicacionLibre'] ?? ''));
  if ($clienteDom === '') {
    $clienteDom = trim(implode(' ', array_filter([
      $cli['Calle'] ?? '', $cli['CodigoPostal'] ?? '',
      $cli['Localidad'] ?? '', $cli['Municipio'] ?? '', $cli['Estado'] ?? '', $cli['Pais'] ?? ''
    ])));
  }

  $asesor = (string)($venta['asesorNombre'] ?? $venta['asesor'] ?? $venta['asesorRef'] ?? $venta['asesorId'] ?? '');

  // 5) Fechas / contrato (acepta varias llaves)
  $fechaIso    = (string)($venta['fecha'] ?? date('Y-m-d'));
  $fechaDMY    = date('d/m/Y', strtotime($fechaIso));
  $numContrato = (string)($venta['contrato'] ?? $venta['contratoNum'] ?? $venta['numContrato'] ?? time());

  // 6) Enganche
  $eng = is_array($venta['enganche'] ?? null) ? $venta['enganche'] : [];
  $engancheTotal = (float)($eng['total'] ?? 0);
  $enganchePlan  = !empty($eng['plan']);
  $engancheModal = (string)($eng['modalidad'] ?? '');
  $engancheCuot  = (int)   ($eng['cuotas'] ?? 0);
  $engancheIni   = (string)($eng['inicio'] ?? '');
  $engCuenta     = (string)($eng['cuenta'] ?? '');
  $engCuentaId   = (string)($eng['cuentaId'] ?? '');

  // 7) Plantilla → prioriza la “bonita”
  $tplCandidates = [
    __DIR__.'/templates/contratos/contrato_base_pretty.docx',
    dirname(__DIR__).'/templates/contratos/contrato_base_pretty.docx',
    __DIR__.'/templates/contratos/contrato_base.docx',
    dirname(__DIR__).'/templates/contratos/contrato_base.docx',
  ];
  $tplPath = null;
  foreach ($tplCandidates as $c) { if (is_file($c)) { $tplPath = $c; break; } }

  // 8) Salida
  $outDir = safe_mkdir(dirname(__DIR__).'/storage/contratos');
  if (!is_dir($outDir) || !is_writable($outDir)) {
    jexit(['ok'=>false,'error'=>'La carpeta /storage/contratos no existe o no tiene permisos de escritura.'], 400);
  }
  $slugCli  = preg_replace('/[^a-z0-9_\-]+/i', '_', mb_strtolower($clienteNombre ?: 'cliente'));
  $baseName = 'Contrato_'.$idDesarrollo.'_'.date('Ymd_His').'_'.$slugCli;
  $docxPath = $outDir.'/'.$baseName.'.docx';
  $pdfPath  = null;

  // 9) DOCX
  if ($tplPath) {
    $tp = new TemplateProcessor($tplPath);

    // Cabecera proyecto
    $tp->setValue('CONTRATO_NUM',  $numContrato);   // ← TU PLANTILLA
    $tp->setValue('CONTRATO_NO',   $numContrato);   // compatibilidad
    $tp->setValue('FECHA_CONTRATO',$fechaDMY);
    $tp->setValue('PROYECTO_NOMBRE',    $proyNombre);
    $tp->setValue('PROYECTO_DIRECCION', $proyDir);
    $tp->setValue('PROYECTO_RESPONSABLE',$proyResp);
    $tp->setValue('PROYECTO_TELEFONO',  $proyTel);
    $tp->setValue('PROYECTO_EMAIL',     $proyEmail);

    // Cliente (nombres usados en tu .docx y alias)
    $tp->setValue('CLIENTE_NOMBRE',        $clienteNombre);
    $tp->setValue('PROMITENTE_NOMBRE',     $clienteNombre);      // alias por si lo usas
    $tp->setValue('CLIENTE_IDENTIFICACION',$clienteCurp ?: '—'); // ← TU PLANTILLA
    $tp->setValue('CLIENTE_CURP',          $clienteCurp ?: '—');
    $tp->setValue('CLIENTE_TELEFONO',      $clienteTel ?: '—');  // ← TU PLANTILLA
    $tp->setValue('CLIENTE_EMAIL',         $clienteEmail ?: '—');
    $tp->setValue('CLIENTE_DOMICILIO',     $clienteDom ?: '—');  // ← TU PLANTILLA

    // Asesor
    $tp->setValue('ASESOR_NOMBRE', dash($asesor));               // ← TU PLANTILLA
    $tp->setValue('ASESOR',        dash($asesor));

    // Cuentas
    for ($i=1; $i<=3; $i++) {
      $c = $cuentas[$i-1] ?? ['Banco'=>'','Beneficiario'=>'','CLABE'=>'','idCuenta'=>''];
      $tp->setValue("CTA{$i}_BANCO", (string)$c['Banco']);
      $tp->setValue("CTA{$i}_BENEF", (string)$c['Beneficiario']);
      $tp->setValue("CTA{$i}_CLABE", (string)$c['CLABE']);
      $tp->setValue("CTA{$i}_IDCUENTA", (string)$c['idCuenta']);
    }

    // Enganche
    $tp->setValue('ENGANCHE_TOTAL',   money_mx($engancheTotal));
    $tp->setValue('ENGANCHE_PLAN_SI', $enganchePlan ? 'SI' : 'NO');
    $tp->setValue('ENGANCHE_MODAL',   $engancheModal ?: '—');
    $tp->setValue('ENGANCHE_CUOTAS',  $engancheCuot ?: '—');
    $tp->setValue('ENGANCHE_INICIO',  $engancheIni ?: '—');
    $tp->setValue('ENGANCHE_CUENTA',  $engCuenta ?: '—');
    $tp->setValue('ENGANCHE_CTA_ID',  $engCuentaId ?: '—');

    // Tabla lotes
    $vars = method_exists($tp, 'getVariables') ? $tp->getVariables() : [];
    if ($rows && in_array('LOTE_NO', $vars, true)) {
      try { $tp->cloneRowAndSetValues('LOTE_NO', $rows); }
      catch (\Throwable $e) {
        throw new \RuntimeException(
          'Plantilla inválida para la tabla de lotes. Debe tener en UNA fila: '.
          '${LOTE_NO}, ${LOTE_MANZANA}, ${LOTE_MEDIDAS}, ${LOTE_DESC}, ${LOTE_PRECIO}. '.$e->getMessage()
        );
      }
    } else {
      $r = $rows[0] ?? ['LOTE_NO'=>'—','LOTE_MANZANA'=>'','LOTE_MEDIDAS'=>'','LOTE_DESC'=>'','LOTE_PRECIO'=>money_mx(0)];
      foreach ($r as $k=>$v) $tp->setValue($k, $v);
    }
    $tp->setValue('TOTAL_CONTRATO', money_mx($total));

    $tp->saveAs($docxPath);
  } else {
    // Fallback sin plantilla
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $s = $phpWord->addSection();
    $s->addTitle('Contrato de Promesa de Compraventa', 1);
    $s->addText("Contrato No.: $numContrato");
    $s->addText("Fecha: $fechaDMY");
    $s->addText("Proyecto: $proyNombre");
    $s->addText("Cliente: $clienteNombre");
    if ($rows) {
      $t = $s->addTable(['borderSize'=>6,'borderColor'=>'999999']);
      $t->addRow(); foreach (['Lote','Manzana','Medidas','Descripción','Precio'] as $th) $t->addCell(2400)->addText($th);
      foreach ($rows as $r) { $t->addRow();
        $t->addCell(2400)->addText($r['LOTE_NO']);
        $t->addCell(2400)->addText($r['LOTE_MANZANA']);
        $t->addCell(2400)->addText($r['LOTE_MEDIDAS']);
        $t->addCell(2400)->addText($r['LOTE_DESC']);
        $t->addCell(2400)->addText($r['LOTE_PRECIO']);
      }
      $s->addText('TOTAL: '.money_mx($total));
    }
    IOFactory::createWriter($phpWord,'Word2007')->save($docxPath);
  }

  // 10) PDF (si dompdf está instalado)
  try {
    if (class_exists(\Dompdf\Dompdf::class)) {
      Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
      Settings::setPdfRendererPath(dirname((new \ReflectionClass(\Dompdf\Dompdf::class))->getFileName()));
      $phpWord = IOFactory::load($docxPath);
      $writer  = IOFactory::createWriter($phpWord, 'PDF');
      $pdfPath = $outDir . '/' . $baseName . '.pdf';
      $writer->save($pdfPath);
    } else { $pdfPath = null; }
  } catch (\Throwable $e) { $pdfPath = null; }

  // 11) URLs
  $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // /pages
  $docxUrl = $baseUrl . '/../storage/contratos/' . basename($docxPath);
  $pdfUrl  = (isset($pdfPath) && $pdfPath && is_file($pdfPath))
           ? ($baseUrl . '/../storage/contratos/' . basename($pdfPath))
           : null;

  jexit([
    'ok'           => true,
    'url'          => $docxUrl,
    'url_docx'     => $docxUrl,
    'url_pdf'      => $pdfUrl,
    'contrato_num' => $numContrato,
    'resumen'      => [
      'proyecto' => [
        'Nombre'=>$proyNombre,'Direccion'=>$proyDir,'Responsable'=>$proyResp,'Telefono'=>$proyTel,'Email'=>$proyEmail,
      ],
      'cliente'  => [
        'Nombre'=>$clienteNombre,'Curp'=>$clienteCurp,'Telefono'=>$clienteTel,'Email'=>$clienteEmail,'Domicilio'=>$clienteDom,
      ],
      'asesor'   => $asesor,
      'lotes'    => $rows,
      'total'    => $total,
      'cuentas'  => array_slice($cuentas, 0, 3),
    ],
  ]);

} catch (\Throwable $e) {
  jexit(['ok'=>false,'error'=>$e->getMessage()], 400);
}
