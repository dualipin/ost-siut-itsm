<?php
declare(strict_types=1);

require_once __DIR__ . '/src/configuracion.php';

$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();

$latte->render(__DIR__ . '/plantillas/avisos.latte');