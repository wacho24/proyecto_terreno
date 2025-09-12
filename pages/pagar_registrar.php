<?php
// pages/pagar_registrar.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ============== DEBUG & ERRORES ==============
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ob_start(); // captura cualquier eco

set_error_handler(function($sev,$msg,$file,$line){
    if(!(error_reporting() & $sev)) return false;
    throw new ErrorException($msg,0,$sev,$file,$line);
});

// helper salida JSON
function respond($arr, int $code=200){
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    session_start();
    require_once __DIR__ . '/../config/firebase_init.php';
    $bucket = fb_bucket(); // bucket real

    $ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

    // -------- INPUT --------
    $idVenta      = trim($_POST['idVenta']      ?? '');
    $idLote       = trim($_POST['idLote']       ?? '');
    $idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/','', $_POST['idDesarrollo'] ?? '');
    $estatus      = trim($_POST['Estatus']      ?? 'CONFIRMADO');
    $fechaPagoISO = trim($_POST['FechaPago']    ?? date('Y-m-d'));
    $formaPago    = trim($_POST['FormaPago']    ?? 'EFECTIVO');
    $referencia   = trim($_POST['Referencia']   ?? '');
    $totalPagoRaw = $_POST['Total'] ?? 0;

    // normalizar total
    $totalPago = floatval(preg_replace('/[^\d.-]/','',$totalPagoRaw));

    if($idVenta==='' || $totalPago<=0){
        respond(['ok'=>false,'error'=>'Faltan datos obligatorios (idVenta / total)'],400);
    }

    // -------- COMPROBANTE --------
    $urlComprobante = '';
    if(isset($_FILES['Comprobante']) && is_uploaded_file($_FILES['Comprobante']['tmp_name'])){
        $tmp  = $_FILES['Comprobante']['tmp_name'];
        $ext  = strtolower(pathinfo($_FILES['Comprobante']['name'], PATHINFO_EXTENSION) ?: 'bin');
        $mime = $_FILES['Comprobante']['type'] ?? 'application/octet-stream';

        $safeDes = $idDesarrollo ?: 'generico';
        $object  = "desarrollos/$safeDes/pagos/$idVenta/".date('Ymd_His')."_".bin2hex(random_bytes(4)).".$ext";

        $bucket->upload(fopen($tmp,'r'), ['name'=>$object,'metadata'=>['contentType'=>$mime]]);
        $urlComprobante = "https://storage.googleapis.com/{$bucket->name()}/$object";
    }

    // -------- REGISTRO --------
    $contPath = "$ROOT_PREFIX/PagosRealizados/$idVenta";
    $database->getReference($contPath)->update([
        'idVenta'=>$idVenta,
        'idLote'=>$idLote
    ]);

    $fechaDMY = DateTime::createFromFormat('Y-m-d',$fechaPagoISO);
    $fechaDMY = $fechaDMY ? $fechaDMY->format('d/m/Y') : $fechaPagoISO;

    $pagoData = [
        'FechaPago'   => $fechaDMY,
        'FormaPago'   => $formaPago,
        'Estatus'     => $estatus,
        'Referencia'  => $referencia,
        'Total'       => $totalPago,
        'Comprobante' => $urlComprobante,
        'createdAt'   => date('c'),
    ];
    $pagoId = $database->getReference("$contPath/PagosRealizados")->push($pagoData)->getKey();

    // -------- RE-CALCULAR ESTADO DE VENTA --------
    $ventaRef = $database->getReference("$ROOT_PREFIX/VentasGenerales/$idVenta");
    $venta    = $ventaRef->getSnapshot()->getValue() ?: [];

    $totalVenta = (float)($venta['PrecioLote'] ?? $venta['Total'] ?? $venta['Precio'] ?? 0);

    $abonado=0.0;
    $pagos = $database->getReference("$contPath/PagosRealizados")->getSnapshot()->getValue() ?: [];
    foreach($pagos as $p){ $abonado += floatval($p['Total'] ?? 0); }

    $nuevoEstatus = ($totalVenta>0 && $abonado+0.01 >= $totalVenta) ? 'LIQUIDADO':'PENDIENTE';
    $ventaRef->update(['Estatus'=>$nuevoEstatus]);

    respond([
        'ok'=>true,
        'pagoId'=>$pagoId,
        'urlComprobante'=>$urlComprobante,
        'abonado'=>$abonado,
        'totalVenta'=>$totalVenta,
        'nuevoEstatus'=>$nuevoEstatus
    ]);

}catch(Throwable $e){
    $buf = ob_get_clean();
    error_log("[pagar_registrar] ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()." OUT:".substr($buf,0,200));
    respond(['ok'=>false,'error'=>$e->getMessage(),'trace'=>$e->getFile().':'.$e->getLine()],500);
}
