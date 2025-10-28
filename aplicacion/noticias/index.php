<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
} else {
    $mensaje = null;
}

$datos = [
        'mensaje' => $mensaje,
];


SesionProtegida::proteger();
ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);