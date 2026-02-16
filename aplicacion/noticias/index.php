<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Modelos\Publicacion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

$publicaciones = Publicacion::obtenerNoticias();

$datos = [
        'publicaciones' => $publicaciones,
        'mensaje' => filter_input(INPUT_GET, 'mensaje', FILTER_SANITIZE_SPECIAL_CHARS),
        'error' => filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS)
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);
