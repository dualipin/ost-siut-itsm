<?php

use App\Manejadores\Sesion;
use App\Manejadores\SesionProtegida;
use App\Modelos\Publicacion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

$miembro = Sesion::sesionAbierta();
$avisos = null;

if ($miembro->esAdmin() || $miembro->esLider()) {
    $avisos = Publicacion::obtenerAvisos();
} else {
    $avisos = Publicacion::buscarAvisosActivosRecientes();
}

$path = $_SERVER['PHP_SELF'];


$datos = [
        'path' => $path,
        'avisos' => $avisos,
        'mensaje' => filter_input(INPUT_GET, 'mensaje', FILTER_SANITIZE_SPECIAL_CHARS),
        'error' => filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS)
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);