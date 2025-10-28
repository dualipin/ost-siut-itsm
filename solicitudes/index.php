<?php

use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/../src/configuracion.php';

$error = $mensaje = null;

if (isset($_GET['error'])) {
    $error = filter_var($_GET['error']);
}

if (isset($_GET['mensaje'])) {
    $mensaje = filter_var($_GET['mensaje']);
}

$datos = [
        'error' => $error,
        'mensaje' => $mensaje,
];

FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/index.latte', $datos);