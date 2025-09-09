<?php
require_once __DIR__ . '/../config/firebase_init.php';

$path = 'projects/proj_8HNCM2DFob/data/AdmisGenerales';
$data = $database->getReference($path)
                 ->orderByKey()       // 👈 define un orderBy
                 ->limitToFirst(1)
                 ->getValue();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
