<?php
declare(strict_types=1);

/**
 * ========== Helpers Globales ==========
 */

/** 
 * Sanitiza ID (para claves Firebase o rutas)
 */
function sid(string $v): string {
  return preg_replace('/[^A-Za-z0-9_\-]/','',$v);
}

/**
 * Limpia string normal
 */
function s(?string $v): string {
  return trim((string)$v);
}

/**
 * Respuesta JSON estándar
 */
function jexit(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

/**
 * Asegura que exista un directorio
 */
function safe_mkdir(string $path): string {
  if (!is_dir($path)) {
    @mkdir($path, 0777, true);
  }
  return $path;
}

/**
 * Carga una venta completa por NumeroContrato
 * 
 * @param $database instancia de Firebase\Database
 * @param string $idDesarrollo ID del desarrollo
 * @param string $ventaId NumeroContrato (ej: 1757365304465)
 */
function load_venta_completa($database, string $idDesarrollo, string $ventaId): ?array {
  $base = "projects/proj_8HNCM2DFob/data/DesarrollosGenerales/$idDesarrollo/VentasGenerales";

  // Traer todas las ventas
  $ventas = $database->getReference($base)->getValue() ?? [];

  // Buscar la venta por NumeroContrato
  $venta = null;
  foreach ($ventas as $k => $v) {
    if (isset($v['NumeroContrato']) && (string)$v['NumeroContrato'] === (string)$ventaId) {
      $venta = $v;
      break;
    }
  }
  if (!$venta) return null;

  // Concatenar lotes si existe el nodo "Lotes"
  $lotesTxt = '';
  if (isset($venta['Lotes']) && is_array($venta['Lotes'])) {
    $parts = [];
    foreach ($venta['Lotes'] as $lot) {
      if (is_array($lot)) {
        $parts[] = $lot['NombreLote'] ?? '';
      } elseif (is_string($lot)) {
        $parts[] = $lot;
      }
    }
    $lotesTxt = implode(', ', array_filter($parts));
  } else {
    $lotesTxt = $venta['NombreLote'] ?? '';
  }

  // Preparar datos para la plantilla
  return [
    'VENTA_ID'        => $ventaId,
    'CLIENTE_NOMBRE'  => $venta['NombreCliente']   ?? '',
    'CLIENTE_CORREO'  => $venta['Correo']          ?? '',
    'CLIENTE_TEL'     => $venta['Telefono']        ?? '',
    'CLIENTE_RFC'     => $venta['RFC']             ?? '',
    'CLIENTE_CURP'    => $venta['CURP']            ?? '',
    'LOTE_DESC'       => $lotesTxt,
    'FECHA_VENTA'     => $venta['FechaVenta']      ?? '',
    'FECHA_INICIO'    => $venta['FechaInicio']     ?? '',
    'FECHA_FIN'       => $venta['FechaFinalizacion'] ?? '',
    'TOTAL_ENGANCHE'  => $venta['Enganche']        ?? '',
    'CUOTAS'          => $venta['CantidadCuotas']  ?? '',
    'MODALIDAD'       => $venta['ModalidadPagos']  ?? '',
    'CUENTA_BANCARIA' => $venta['CuentaBancaria']  ?? '',
    'VENDEDOR'        => $venta['NombreVendedor']  ?? '',
    'CONTRATO_NUM'    => $venta['NumeroContrato']  ?? '',
    'ESTATUS'         => $venta['Estatus']         ?? '',
  ];
}
