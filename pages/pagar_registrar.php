<?php
// pages/pagar_registrar.php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

header('Content-Type: application/json; charset=utf-8');

$JSON = static function($arr, int $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
};

try {
    // Firebase
    require_once __DIR__ . '/../config/firebase_init.php';
    /** @var \Kreait\Firebase\Database $database */
    /** @var \Google\Cloud\Storage\Bucket $bucket */
    $bucket = fb_bucket();

    // Raíz de tu RTDB (mantén igual que en tus otras páginas)
    $ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

    // -------- Helpers --------
    $str = static function($v): string {
        return trim((string)$v);
    };
    $num = static function($v): float {
        if (is_numeric($v)) return (float)$v;
        $s = trim((string)$v);
        $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '0';
        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            // el último separador decide
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif (strpos($s, ',') !== false) {
            // ¿coma como decimal?
            if (preg_match('/,\d{1,2}$/', $s)) $s = str_replace(',', '.', $s);
            else $s = str_replace(',', '', $s);
        }
        return (float)$s;
    };
    $fmtDMY = static function(?string $iso): string {
        $iso = trim((string)$iso);
        if ($iso === '') return date('d/m/Y');
        // si ya viene d/m/Y lo dejamos
        if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $iso)) return $iso;
        $t = strtotime($iso);
        if ($t === false) $t = time();
        return date('d/m/Y', $t);
    };

    // -------- Entrada (multipart/form-data) --------
    $idDesarrollo = $str($_POST['idDesarrollo'] ?? '');
    $idVenta      = $str($_POST['idVenta'] ?? '');
    $idLote       = $str($_POST['idLote'] ?? ''); // puede ser id o label; lo guardamos tal cual como "idLote" en el padre
    $estatus      = $str($_POST['Estatus'] ?? 'CONFIRMADO');
    $fechaPago    = $fmtDMY($str($_POST['FechaPago'] ?? ''));
    $formaPago    = $str($_POST['FormaPago'] ?? '');
    $referencia   = $str($_POST['Referencia'] ?? '');
    $total        = $num($_POST['Total'] ?? 0);

    if ($idVenta === '' || $total <= 0) {
        $JSON(['ok'=>false, 'error'=>'Faltan datos obligatorios (idVenta y total).'], 400);
    }

    // -------- Subir archivo (opcional) --------
    $urlComprobante = '';
    if (!empty($_FILES['Comprobante']) && is_array($_FILES['Comprobante'])) {
        $f = $_FILES['Comprobante'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
            $orig  = (string)$f['name'];
            $ext   = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext === '') $ext = 'bin';

            // subimos provisionalmente con nombre temporal; si luego queremos,
            // podríamos renombrar cuando tengamos el pushId.
            $dest  = 'desarrollos/'
                   . ($idDesarrollo !== '' ? $idDesarrollo : '_na')
                   . '/pagos/'
                   . rawurlencode($idVenta)
                   . '/' . uniqid('tmp_', true) . '.' . $ext;

            $obj = $bucket->upload(
                fopen($f['tmp_name'], 'r'),
                [
                    'name' => $dest,
                    'metadata' => [
                        'cacheControl' => 'public, max-age=31536000'
                    ],
                    'predefinedAcl' => 'publicRead'
                ]
            );

            // URL pública
            $urlComprobante = 'https://storage.googleapis.com/' . $bucket->name() . '/' . $dest;
        }
    }

    // -------- Crear/actualizar padre: PagosRealizados/{idVenta} --------
    $padrePath = "$ROOT_PREFIX/PagosRealizados/$idVenta";
    $padreRef  = $database->getReference($padrePath);

    // Si no existe, lo creamos con idLote si vino
    $padreSnap = $padreRef->getSnapshot();
    if (!$padreSnap->exists()) {
        $padreRef->set([
            'idLote' => $idLote,          // puede quedar vacío si no mandaste lote
        ]);
    } else {
        // si nos pasaron un lote ahora y el padre no lo tiene, lo guardamos
        $padre = $padreSnap->getValue() ?: [];
        if ($idLote !== '' && empty($padre['idLote'])) {
            $padreRef->update(['idLote' => $idLote]);
        }
    }

    // -------- Insertar pago en sublista --------
    $subPath  = "$padrePath/PagosRealizados";
    $pushRef  = $database->getReference($subPath)->push(); // genera key
    $pagoId   = $pushRef->getKey();

    // Si subimos con nombre temporal, podemos mover/duplicar con el id definitivo (opcional).
    // Para mantenerlo simple, dejamos la subida como está.
    $pagoData = [
        'Comprobante' => $urlComprobante,  // '' si no hay
        'Estatus'     => $estatus ?: 'CONFIRMADO',
        'FechaPago'   => $fechaPago,       // d/m/Y
        'FormaPago'   => $formaPago,
        'Referencia'  => $referencia,
        'Total'       => $total,
    ];
    $pushRef->set($pagoData);

    // -------- Recalcular y liquidar si ya cubrió el total --------
    $recalcAndMaybeLiquidar = static function(\Kreait\Firebase\Database $db, string $root, string $ventaId): void {
        $ventaPath = "$root/VentasGenerales/$ventaId";
        $ventaSnap = $db->getReference($ventaPath)->getSnapshot();
        $venta     = $ventaSnap->getValue() ?: [];

        // Total de la venta (ajusta claves si las usas diferente)
        $totalVenta = (float)($venta['PrecioLote'] ?? $venta['Total'] ?? 0);
        if ($totalVenta <= 0) return;

        $pagosPath = "$root/PagosRealizados/$ventaId/PagosRealizados";
        $pagosSnap = $db->getReference($pagosPath)->getSnapshot();
        $pagos     = $pagosSnap->getValue() ?: [];

        $abonado = 0.0;
        foreach ($pagos as $p) {
            $st = strtoupper((string)($p['Estatus'] ?? 'CONFIRMADO'));
            if ($st === 'CONFIRMADO') {
                $abonado += (float)($p['Total'] ?? 0);
            }
        }

        $newStatus = ($abonado + 0.005 >= $totalVenta) ? 'LIQUIDADO' : 'PENDIENTE';
        $db->getReference($ventaPath.'/Estatus')->set($newStatus);
    };

    $recalcAndMaybeLiquidar($database, $ROOT_PREFIX, $idVenta);

    // -------- OK --------
    $JSON([
        'ok' => true,
        'pagoId' => $pagoId,
        'urlComprobante' => $urlComprobante
    ]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    // cuando viene error del bucket, conviene devolver el JSON plano del proveedor si existe
    if (method_exists($e, 'getServiceException') && $e->getServiceException()) {
        $se = $e->getServiceException();
        $msg = $se->getMessage();
    }
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
