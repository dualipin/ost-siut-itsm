<?php
declare(strict_types=1);

use App\Fabricas\FabricaLatte;
use App\Modelos\Publicacion;

require_once __DIR__ . '/src/configuracion.php';

$avisos = Publicacion::buscarPorTipo('aviso');

$datos = ['avisos' => $avisos];

FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/avisos.latte', $datos);