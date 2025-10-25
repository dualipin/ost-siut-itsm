<?php

declare(strict_types=1);

use App\Fabricas\FabricaConexion;
use App\Manejadores\Sesion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;
use App\Servicios\ServicioMiembros;
use App\Servicios\ServicioPrestamos;

require_once __DIR__ . '/../../src/configuracion.php';

$mensajeError = null;

SesionProtegida::proteger();

try {
    $pdo = FabricaConexion::crear();
} catch (Exception $e) {
    $mensajeError = $e->getMessage();
}

$servicioPrestamos = new ServicioPrestamos($pdo);
$servicioMiembros = new ServicioMiembros($pdo);

// Obtener miembro actual
$usuarioId = Sesion::idSesionAbierta();
$miembro = $servicioMiembros->obtenerPorUsuario($usuarioId);
$solicitudes = [];

if ($miembro) {
    $solicitudes = $servicioPrestamos->obtenerSolicitudesPorMiembro($miembro->id);
}

$datos = [
        'solicitudes' => $solicitudes,
        'mensajeError' => $mensajeError,
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);
