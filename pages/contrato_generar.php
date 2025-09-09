<?php
// pages/contrato_generar.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\IOFactory;

require_once __DIR__ . '/../vendor/autoload.php';

function safe_mkdir(string $dir): string {
  $real = realpath($dir);
  if ($real === false) {
    @mkdir($dir, 0775, true);
    $real = realpath($dir);
  }
  if ($real === false) {
    // como último recurso, regresamos dir original (puede existir pero sin realpath)
    $real = $dir;
  }
  return $real;
}

try {
  // ===== 1) Entrada =====
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new RuntimeException('No se pudo leer la entrada.');
  $in = json_decode($raw, true);
  if (!is_array($in)) throw new RuntimeException('JSON inválido.');

  $venta = $in['venta'] ?? null;
  if (!$venta) throw new RuntimeException('Faltan datos de la venta.');

  // Campos esperados (del listado)
  $cliente    = (string)($venta['cliente']   ?? '');
  $fecha      = (string)($venta['fecha']     ?? '');
  $tipo       = (string)($venta['tipo']      ?? '');
  $total      = (float) ($venta['total']     ?? 0);
  $contratoN  = (string)($venta['contrato']  ?? '');
  $desarrollo = (string)($in['idDesarrollo'] ?? ($venta['idDesarrollo'] ?? ''));
  $vendedor   = (string)($venta['vendedor']  ?? $venta['asesor'] ?? '');
  $lotes      = $venta['lotes'] ?? []; // array de [{label, precio}] cuando disponibles
  $lotePlano  = (string)($venta['lote'] ?? '');

  $estado = (stripos($tipo, 'credito') !== false) ? 'PENDIENTE' : 'LIQUIDADO';

  // ===== 2) Ubicar plantilla (varios candidatos) =====
  $candidates = [
    __DIR__ . '/templates/contratos/contrato_base.docx', // pages/templates/...
    dirname(__DIR__) . '/templates/contratos/contrato_base.docx', // /templates/...
    dirname(__DIR__) . '/assets/templates/contratos/contrato_base.docx',
    getcwd() . '/templates/contratos/contrato_base.docx',
    __DIR__ . '/../contratos/contrato_base.docx',
  ];
  $tplPath = null;
  foreach ($candidates as $c) {
    if (is_file($c)) { $tplPath = $c; break; }
  }

  // ===== 3) Salida (directorio) =====
  $outDir = safe_mkdir(__DIR__ . '/../storage/contratos');
  if (!is_dir($outDir) || !is_writable($outDir)) {
    throw new RuntimeException('La carpeta /storage/contratos no existe o no tiene permisos de escritura.');
  }

  $slugCliente = preg_replace('/[^A-Za-z0-9_\-]+/', '_', mb_strtolower($cliente ?: 'cliente'));
  $fileBase    = 'contrato_' . date('Ymd_His') . '_' . $slugCliente;
  $docxPath    = rtrim($outDir, '/\\') . '/' . $fileBase . '.docx';
  $pdfPath     = null;

  // ===== 4) Generar DOCX =====
  if ($tplPath) {
    // ---- Con plantilla .docx ----
    $tp = new TemplateProcessor($tplPath);
    $tp->setValue('CLIENTE',    $cliente);
    $tp->setValue('VENDEDOR',   $vendedor);
    $tp->setValue('FECHA',      $fecha);
    $tp->setValue('TIPO_VENTA', $tipo);
    $tp->setValue('TOTAL',      number_format($total, 2, '.', ','));
    $tp->setValue('CONTRATO',   $contratoN ?: 'Por Generar');
    $tp->setValue('DESARROLLO', $desarrollo);
    $tp->setValue('ESTADO',     $estado);

    // Lotes: si la plantilla tiene fila con placeholders LOTE_DESC/LOTE_PRECIO
    $rows = [];
    if (is_array($lotes) && count($lotes)) {
      foreach ($lotes as $it) {
        $rows[] = [
          'LOTE_DESC'   => (string)($it['label'] ?? ''),
          'LOTE_PRECIO' => number_format((float)($it['precio'] ?? 0), 2, '.', ','),
        ];
      }
      try {
        $tp->cloneRowAndSetValues('LOTE_DESC', $rows);
      } catch (\Throwable $e) {
        // si la plantilla NO tenía esa fila, colocamos un resumen
        $tp->setValue('LOTE_DESC', implode(', ', array_column($rows,'LOTE_DESC')));
        $tp->setValue('LOTE_PRECIO', number_format($total, 2, '.', ','));
      }
    } else {
      $tp->setValue('LOTE_DESC', $lotePlano);
      $tp->setValue('LOTE_PRECIO', number_format($total, 2, '.', ','));
    }

    $tp->saveAs($docxPath);
  } else {
    // ---- SIN plantilla: generamos un DOCX básico ----
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addTitle('Contrato de compraventa', 1);
    $section->addText("Contrato N°: " . ($contratoN ?: 'Por Generar'));
    $section->addText("Cliente: $cliente");
    $section->addText("Vendedor: $vendedor");
    $section->addText("Fecha: $fecha");
    $section->addText("Desarrollo: $desarrollo");
    $section->addText("Tipo de venta: $tipo ($estado)");
    $section->addTextBreak();

    if (is_array($lotes) && count($lotes)) {
      $section->addText('Detalle de lotes:');
      $table = $section->addTable(['borderSize'=>6, 'borderColor'=>'999999']);
      $table->addRow();
      $table->addCell(8000)->addText('Descripción');
      $table->addCell(2000)->addText('Precio');
      foreach ($lotes as $it) {
        $table->addRow();
        $table->addCell(8000)->addText((string)($it['label'] ?? ''));
        $table->addCell(2000)->addText(number_format((float)($it['precio'] ?? 0), 2, '.', ','));
      }
    } else {
      $section->addText("Lote(s): " . ($lotePlano ?: '—'));
    }
    $section->addTextBreak();
    $section->addText('Total: $' . number_format($total, 2, '.', ','));

    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($docxPath);
  }

  // ===== 5) (Opcional) PDF =====
  try {
    Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
    Settings::setPdfRendererPath(dirname((new ReflectionClass(\Dompdf\Dompdf::class))->getFileName()));
    $phpWord = IOFactory::load($docxPath);
    $writer  = IOFactory::createWriter($phpWord, 'PDF');
    $pdfPath = rtrim($outDir, '/\\') . '/' . $fileBase . '.pdf';
    $writer->save($pdfPath);
  } catch (\Throwable $e) {
    $pdfPath = null; // si falla no cortamos
  }

  // ===== 6) URLs (compatibles con tu frontend) =====
  $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // /pages
  $docxUrl = $baseUrl . '/../storage/contratos/' . basename($docxPath);
  $pdfUrl  = $pdfPath ? ($baseUrl . '/../storage/contratos/' . basename($pdfPath)) : null;

  echo json_encode([
    'ok'       => true,
    'url'      => $docxUrl,  // compat con tu Swal actual
    'url_docx' => $docxUrl,
    'url_pdf'  => $pdfUrl,
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
