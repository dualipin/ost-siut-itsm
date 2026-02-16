<?php

use App\Enums\Intereses;
use Dompdf\Dompdf;

require_once __DIR__ . '/../../src/configuracion.php';
require_once __DIR__.'/generador-corrida.php';


$monto = $_POST["monto"] ?? 10000;
$tasaTipo = $_POST["tasa"] ?? 'ahorrador_no_agremiado';
$plazo = $_POST["plazo"] ?? 12;
$tasa = Intereses::AHORRADOR_NO_AGREMIADO;

if ($tasaTipo == 'ahorrador_agremiado') {
    $tasa = Intereses::AHORRADOR_AGREMIADO;
} elseif ($tasaTipo == 'no_ahorrador_agremiado') {
    $tasa = Intereses::NO_AHORRADOR_AGREMIADO;
}

$tablaAmortizacion = calcularInteres($monto, $tasa->value, $plazo);
$totales = calcularPagoTotal($tablaAmortizacion);

$datos = [
        'monto' => $monto,
        'tasa' => $tasa->value,
        'plazo' => $plazo,
        'tabla_amortizacion' => $tablaAmortizacion,
        'totales' => $totales,
];

$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();
$html = $latte->renderToString(__DIR__ . '/pdf-simulador.latte', $datos);

$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();

$pdf->stream('agremiados.pdf', ['Attachment' => true]);

