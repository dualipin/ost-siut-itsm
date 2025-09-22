<?php

use App\Modelos\Visita;
use App\Utilidades\Numero;

/* @var Latte\Engine $latte */
$latte = require_once __DIR__ . '/src/latte.php';

Visita::agregarVisita();

$visitas = Visita::obtenerVisitasHoy();

$latte->render(__DIR__ . '/plantillas/index.latte', [
        'visitaPagina' => Numero::formatearNumero($visitas),
]);
