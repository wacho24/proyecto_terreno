<?php
// pages/ventas_list.php
require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$_GET['id']) : '';
if ($id === '') { echo json_encode(['ok'=>false,'error'=>'id vacío']); exit; }

require_once __DIR__ . '/../config/firebase_init.php';
/** @var \Kreait\Firebase\Database $database */
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

try {
  // === 1) Cargar ventas (estructura legacy que ya usabas)
  $ventasPath = "$ROOT_PREFIX/VentasGenerales";
  $snap = $database->getReference($ventasPath)->getSnapshot();
  $rows = $snap->getValue() ?: [];

  $items = [];

  foreach ($rows as $rid => $row) {
    if (!is_array($row)) continue;

    // Filtrado por desarrollo (acepta idDesarrollo o IdDesarrollo)
    $idDesRow = (string)($row['idDesarrollo'] ?? $row['IdDesarrollo'] ?? '');
    if ($idDesRow !== '' && $idDesRow !== $id) continue;

    // Campos base (legacy)
    $cliente    = (string)($row['NombreCliente']   ?? '');
    $loteLbl    = (string)($row['NombreLote']      ?? '');
    $fecha      = (string)($row['FechaVenta']      ?? '');
    $tipo       = (string)($row['TipoVenta']       ?? '');
    $contrato   = (string)($row['NumeroContrato']  ?? '');
    // Total de la venta: intenta Total, luego PrecioLote
    $total      = (float)  ($row['Total'] ?? $row['PrecioLote'] ?? 0);

    // === 2) Sumar pagos registrados
    $pagosPath  = "$ROOT_PREFIX/PagosRealizados/$rid";
    $pagosSnap  = $database->getReference($pagosPath)->getSnapshot();
    $pagado     = 0.0;

    if ($pagosSnap->exists()) {
      $pagos = $pagosSnap->getValue() ?: [];
      foreach ($pagos as $pid => $p) {
        // solo suma pagos confirmados si manejas estatus
        $estatusPago = strtoupper((string)($p['Estatus'] ?? $p['estatus'] ?? 'CONFIRMADO'));
        $monto       = (float)($p['Total'] ?? $p['total'] ?? 0);
        if ($monto > 0 && $estatusPago !== 'RECHAZADO' && $estatusPago !== 'ANULADO') {
          $pagado += $monto;
        }
      }
    } else {
      // Si no existe nodo de pagos, intenta algún total previo guardado
      $pagado = (float)($row['TotalPagado'] ?? 0);
    }

    // === 3) Estado derivado
    $estadoActual = (string)($row['Estatus'] ?? $row['Estado'] ?? '');
    $estadoCalc   = ($total > 0 && $pagado + 0.0001 >= $total) ? 'LIQUIDADO' : 'PENDIENTE';
    $estadoFinal  = $estadoActual !== '' ? $estadoActual : $estadoCalc;

    // (opcional) Persistir TotalPagado + Estatus para que el visor/ojito lo vea actualizado
    try {
      $database->getReference("$ventasPath/$rid")->update([
        'TotalPagado' => $pagado,
        'Estatus'     => $estadoFinal,
      ]);
    } catch (\Throwable $e) {
      // silencioso
    }

    // === 4) Armar item compatible con la UI nueva
    $items[] = [
      'id'           => $rid,
      'cliente'      => $cliente,
      'lote'         => $loteLbl,
      // (si en el futuro migras a múltiples lotes, aquí puedes llenar 'lotes' como arreglo)
      'fecha'        => $fecha,
      'tipo'         => $tipo,
      'total'        => $total,
      'totalPagado'  => $pagado,
      'estado'       => $estadoFinal,   // usado por la UI nueva
      'estatus'      => $estadoFinal,   // compatibilidad con legacy
      'contrato'     => $contrato,
      'clienteId'    => (string)($row['IdCliente'] ?? $row['idCliente'] ?? ''),
    ];
  }

  // === 5) Ordenar por fecha (DD/MM/YYYY) descendente
  usort($items, function($a,$b){
    $pa = DateTime::createFromFormat('d/m/Y', (string)$a['fecha']) ?: new DateTime('1970-01-01');
    $pb = DateTime::createFromFormat('d/m/Y', (string)$b['fecha']) ?: new DateTime('1970-01-01');
    return $pb <=> $pa;
  });

  echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
