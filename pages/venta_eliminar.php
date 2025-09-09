<?php
// pages/venta_eliminar.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new Exception('Sin cuerpo');

  $in = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $idVenta       = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($in['idVenta'] ?? ''));
  $idDesarrollo  = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($in['idDesarrollo'] ?? ''));

  if ($idVenta === '') {
    throw new Exception('Faltan parámetros: idVenta');
  }

  require_once __DIR__ . '/../config/firebase_init.php';
  $ROOT = 'projects/proj_8HNCM2DFob/data';

  // Rutas a eliminar:
  // 1) Nodo usado por el dashboard (donde registras ventas)
  $pathVentasGenerales = "$ROOT/VentasGenerales/$idVenta";
  // 2) Nodo por desarrollo (usado por Apphive)
  $pathPorDesarrollo   = ($idDesarrollo !== '')
    ? "$ROOT/DesarrollosGenerales/$idDesarrollo/Ventas/$idVenta"
    : null;

  $deleted = [];
  $warnings = [];

  // Eliminar en VentasGenerales (borra también subnodos: Lotes, Pagos)
  try {
    $database->getReference($pathVentasGenerales)->remove();
    $deleted[] = $pathVentasGenerales;
  } catch (Throwable $e) {
    $warnings[] = "No se pudo eliminar en VentasGenerales: " . $e->getMessage();
  }

  // Eliminar en DesarrollosGenerales/{idDesarrollo}/Ventas (si aplica)
  if ($pathPorDesarrollo) {
    try {
      $database->getReference($pathPorDesarrollo)->remove();
      $deleted[] = $pathPorDesarrollo;
    } catch (Throwable $e) {
      $warnings[] = "No se pudo eliminar en DesarrollosGenerales: " . $e->getMessage();
    }
  }

  if (empty($deleted)) {
    // Si nada se pudo eliminar, márcalo como error
    throw new Exception('No se encontró la venta en las rutas esperadas.');
  }

  echo json_encode([
    'ok'       => true,
    'deleted'  => $deleted,
    'warnings' => $warnings
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
