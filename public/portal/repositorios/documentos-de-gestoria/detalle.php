<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../../src/configuracion.php';
require_once __DIR__ . '/buscar.php';

SesionProtegida::proteger();

if (!isset($_GET['id_doc']) || !is_numeric($_GET['id_doc'])) {
    http_response_code(400);
    header('Location: index.php');
    exit('ID de documento inválido.');
}

$conn = MysqlConexion::conexion();

$documento = buscarDocumento($conn, $_GET['id_doc']);

if (!$documento) {
    http_response_code(404);
    $error = 'Documento no encontrado.';
    header('Location: index.php?error=' . urlencode($error));
    exit('Documento no encontrado.');
}


$id = (int)$_GET['id_doc'];
$data = [
        'documento' => $documento
];


ServicioLatte::renderizar(__DIR__ . '/detalle.latte', $data);