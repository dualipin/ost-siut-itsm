<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;
use App\Servicios\ServicioPrestamos;
use App\Servicios\ServicioMiembros;
use App\Fabricas\FabricaConexion;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

$pdo = FabricaConexion::crear();
$servicioPrestamos = new ServicioPrestamos($pdo);
$servicioMiembros = new ServicioMiembros($pdo);

// Obtener miembro actual
$miembro = $servicioMiembros->obtenerPorUsuario($_SESSION['usuario_id']);
$solicitudes = [];

if ($miembro) {
  $solicitudes = $servicioPrestamos->obtenerSolicitudesPorMiembro($miembro->id);
}

$datos = [
  'solicitudes' => $solicitudes,
  'miembro' => $miembro
];

ServicioLatte::renderizar(__DIR__ . '/../plantillas/prestamos.latte', $datos);
