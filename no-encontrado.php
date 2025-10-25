<?php
declare(strict_types=1);

use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/src/configuracion.php';

header('HTTP/1.1 404 Not Found');
FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/no-encontrado.latte');
