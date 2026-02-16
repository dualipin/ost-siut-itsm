<?php

use App\Manejadores\SesionProtegida;
use App\Modelos\Publicacion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';
SesionProtegida::proteger();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$publicacion = Publicacion::buscarPorId($id);

ServicioLatte::renderizar(__DIR__ . '/detalle.latte', [
        'publicacion' => $publicacion,
]);