<?php

use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

ServicioLatte::renderizar(__DIR__.'/detalle.latte', ['id' => $_GET['id']]);