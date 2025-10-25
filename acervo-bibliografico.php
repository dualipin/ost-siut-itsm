<?php

use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/src/configuracion.php';

$latte = FabricaLatte::obtenerInstancia();
$latte->render(__DIR__ . '/acervo-bibliografico.latte');