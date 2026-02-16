<?php

use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/../src/configuracion.php';

FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/index.latte');