<?php

use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-type: application/json');
    $req = json_decode(file_get_contents('php://input'), true);

//    echo json_encode($req);
    echo json_encode(['success' => true, 'data' => $req]);
//    echo json_encode(['estado' => 'ok', 'mensaje' => 'Acción realizada con éxito']);
    exit;
}


ServicioLatte::renderizar(__DIR__ . '/gestionar.latte');