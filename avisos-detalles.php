<?php

use App\Fabricas\FabricaLatte;
use App\Modelos\Publicacion;

require_once __DIR__ . '/src/configuracion.php';


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /avisos.php');
    exit;
}

$aviso = Publicacion::buscarPorId($_GET['id']);

if (!$aviso) {
    header('Location: /avisos.php');
    exit;
}

$datos = [
        'publicacion' => $aviso
];

FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/avisos-detalles.latte', $datos);