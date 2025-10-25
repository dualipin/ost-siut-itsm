<?php
declare(strict_types=1);

use App\Fabricas\FabricaLatte;
use App\Modelos\Visita;
use App\Utilidades\Numero;

require_once __DIR__ . '/src/configuracion.php';

$latte = FabricaLatte::obtenerInstancia();

Visita::agregarVisita();

$visitas = Visita::obtenerVisitasHoy();

$latte->render(__DIR__ . '/index.latte', [
        'visitaPagina' => Numero::formatearNumero($visitas),
]);
