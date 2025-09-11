<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Si tu init hace session / guard, déjalo:
require_once __DIR__ . '/../config/firebase_init.php';

// ========= Utilidad segura para JSON de retorno rápido =========
function jexit($arr){
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// ========= Entradas =========
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !isset($data['idVenta']) || !isset($data['idDesarrollo'])) {
  jexit(["ok"=>false, "error"=>"Parámetros inválidos o incompletos"]);
}

$idVenta      = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$data['idVenta']);
$idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$data['idDesarrollo']);
$venta        = is_array($data['venta'] ?? null) ? $data['venta'] : [];

// ========= Rutas / archivos =========
$rootContratos = realpath(__DIR__ . '/../') ?: dirname(__DIR__);
$dirContratos  = $rootContratos . '/contratos';
if (!is_dir($dirContratos)) { @mkdir($dirContratos, 0777, true); }

$filenamePdf = "contrato_{$idVenta}.pdf";
$filePathPdf = $dirContratos . '/' . $filenamePdf;

// URL pública correcta (desde /pages a /..)
$scriptDir = dirname($_SERVER['SCRIPT_NAME']); // /proyecto_terreno/pages
$urlPdf    = $scriptDir . '/../contratos/' . $filenamePdf;

// ========= Armar mapa de datos (con defaults seguros) =========
$cliente     = (string)($venta['cliente'] ?? $venta['clienteLabel'] ?? '');
$contrato    = (string)($venta['contrato'] ?? $idVenta);
$fecha       = (string)($venta['fecha'] ?? '');
$tipo        = (string)($venta['tipo'] ?? '');
$totalNum    = 0;
if (isset($venta['total'])) {
  $totalNum = (float)$venta['total'];
} elseif (is_array($venta['lotes'] ?? null)) {
  foreach ($venta['lotes'] as $it) $totalNum += (float)($it['precio'] ?? 0);
}
$totalTxt    = '$ '.number_format($totalNum, 2, '.', ',');

$asesor      = (string)($venta['asesorRef'] ?? $venta['asesorId'] ?? '');
$obs         = (string)($venta['obs'] ?? '');
$lotesArr    = is_array($venta['lotes'] ?? null) ? $venta['lotes'] : [];
$loteResumen = count($lotesArr)
  ? implode(', ', array_map(fn($it)=> (string)($it['label'] ?? $it['id'] ?? ''), $lotesArr))
  : (string)($venta['lote'] ?? '');

$eng = is_array($venta['enganche'] ?? null) ? $venta['enganche'] : [];
$engancheTotal   = isset($eng['total']) ? (float)$eng['total'] : 0;
$engTotalTxt     = '$ '.number_format($engancheTotal, 2, '.', ',');
$planModalidad   = (string)($eng['modalidad'] ?? '');
$planCuotas      = (string)($eng['cuotas'] ?? '');
$planInicio      = (string)($eng['inicio'] ?? '');
$planFin         = (string)($eng['fin'] ?? '');

// ========= Plantilla DOCX (si existe) =========
$templatePath = $rootContratos . '/../templates/contrato_base_pretty_placeholders.docx';
$templateExists = is_file($templatePath);

// ========= Intentar DOCX -> PDF con PHPWord + Dompdf =========
if ($templateExists) {
  try {
    require_once __DIR__ . '/../vendor/autoload.php';

    // Reemplazo de placeholders
    if (class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
      $tpl = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

      // Set placeholders (ajusta los nombres a tu .docx)
      $tpl->setValue('CLIENTE',       $cliente);
      $tpl->setValue('CONTRATO',      $contrato);
      $tpl->setValue('FECHA',         $fecha);
      $tpl->setValue('TIPO',          $tipo);
      $tpl->setValue('TOTAL',         $totalTxt);
      $tpl->setValue('ASESOR',        $asesor);
      $tpl->setValue('DESARROLLO',    $idDesarrollo);
      $tpl->setValue('LOTE_RESUMEN',  $loteResumen);
      $tpl->setValue('OBS',           $obs);

      $tpl->setValue('ENGANCHE_TOTAL', $engTotalTxt);
      $tpl->setValue('PLAN_MODALIDAD', $planModalidad);
      $tpl->setValue('PLAN_CUOTAS',    $planCuotas);
      $tpl->setValue('PLAN_INICIO',    $planInicio);
      $tpl->setValue('PLAN_FIN',       $planFin);

      // Guardar DOCX temporal
      $tmpDocx = $dirContratos . "/_tmp_{$idVenta}.docx";
      $tpl->saveAs($tmpDocx);

      // Configurar renderer PDF (dompdf o mPDF). Aquí uso dompdf.
      \PhpOffice\PhpWord\Settings::setPdfRendererName(\PhpOffice\PhpWord\Settings::PDF_RENDERER_DOMPDF);
      \PhpOffice\PhpWord\Settings::setPdfRendererPath(__DIR__ . '/../vendor/dompdf/dompdf');

      // Cargar DOCX y exportar a PDF
      $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpDocx);
      $pdfWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
      $pdfWriter->save($filePathPdf);
      @unlink($tmpDocx); // limpiar temporal

      jexit(["ok"=>true, "url"=>$urlPdf]);
    }
    // Si no está PHPWord/TemplateProcessor, cae al FPDF de abajo
  } catch (\Throwable $e) {
    // Si falla conversión, cae al FPDF de abajo
    // Puedes loguear $e->getMessage() en /logs si quieres
  }
}

// ========= Fallback a FPDF (PDF simple) =========
try {
  require_once __DIR__ . '/../vendor/autoload.php';
  if (!class_exists('FPDF')) {
    // Si no está FPDF en vendor, inclúyelo manualmente si lo tienes:
    // require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';
  }

  $pdf = new FPDF();
  $pdf->AddPage();
  $pdf->SetFont('Arial','B',16);
  $pdf->Cell(0,10,utf8_decode("Contrato de Venta"),0,1,'C');

  $pdf->Ln(5);
  $pdf->SetFont('Arial','',12);
  $pdf->MultiCell(0,7,utf8_decode("ID Venta: $idVenta"));
  $pdf->MultiCell(0,7,utf8_decode("Desarrollo: $idDesarrollo"));
  $pdf->MultiCell(0,7,utf8_decode("Contrato: $contrato"));
  $pdf->MultiCell(0,7,utf8_decode("Cliente: $cliente"));
  $pdf->MultiCell(0,7,utf8_decode("Fecha: $fecha"));
  $pdf->MultiCell(0,7,utf8_decode("Tipo: $tipo"));
  $pdf->MultiCell(0,7,utf8_decode("Total: $totalTxt"));
  $pdf->MultiCell(0,7,utf8_decode("Lote(s): $loteResumen"));

  if ($planModalidad || $planCuotas || $planInicio) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,7,utf8_decode("Plan de Enganche"),0,1);
    $pdf->SetFont('Arial','',12);
    $pdf->MultiCell(0,7,utf8_decode("Enganche: $engTotalTxt"));
    $pdf->MultiCell(0,7,utf8_decode("Modalidad: $planModalidad"));
    $pdf->MultiCell(0,7,utf8_decode("Cuotas: $planCuotas"));
    $pdf->MultiCell(0,7,utf8_decode("Inicio: $planInicio"));
    if ($planFin) $pdf->MultiCell(0,7,utf8_decode("Fin: $planFin"));
  }

  if ($obs) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,7,utf8_decode("Observaciones"),0,1);
    $pdf->SetFont('Arial','',12);
    $pdf->MultiCell(0,7,utf8_decode($obs));
  }

  $pdf->Output('F', $filePathPdf);

  jexit(["ok"=>true, "url"=>$urlPdf]);

} catch (\Throwable $e) {
  jexit(["ok"=>false, "error"=>"Error generando PDF: ".$e->getMessage()]);
}
