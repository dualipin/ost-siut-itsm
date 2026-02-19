<?php

declare(strict_types=1);

use App\Shared\Context\Intereses;
use Dompdf\Dompdf;
use App\Bootstrap;
use App\Module\Prestamo\Service\SimuladorService;
use DateTimeImmutable;

require_once __DIR__ . '/../../src/configuracion.php';

// Obtener parámetros
$monto = isset($_GET["monto"]) ? floatval($_GET["monto"]) : (isset($_POST["monto"]) ? floatval($_POST["monto"]) : 10000);
$tasaTipo = $_GET["tasa"] ?? $_POST["tasa"] ?? 'ahorrador_no_agremiado';
$plazo = isset($_GET["plazo"]) ? intval($_GET["plazo"]) : (isset($_POST["plazo"]) ? intval($_POST["plazo"]) : 12);

// Seleccionar tipo de interés
$tasa = Intereses::AHORRADOR_NO_AGREMIADO;
if ($tasaTipo === 'ahorrador_agremiado') {
    $tasa = Intereses::AHORRADOR_AGREMIADO;
} elseif ($tasaTipo === 'no_ahorrador_agremiado') {
    $tasa = Intereses::NO_AHORRADOR_AGREMIADO;
}

// Generar simulación usando el servicio
$container = Bootstrap::buildContainer();
$simuladorService = $container->get(SimuladorService::class);

try {
    $resultado = $simuladorService->simular(
        $monto,
        $tasa,
        $plazo,
        new DateTimeImmutable()
    );

    $datos = [
        'monto' => number_format($monto, 2, ',', '.'),
        'tasa' => $tasa->valor(),
        'plazo' => $plazo,
        'corrida' => $resultado['corrida'],
        'totales_capital' => $resultado['totales']['totalCapital'],
        'totales_interes' => $resultado['totales']['totalInteres'],
        'totales_pago' => $resultado['totales']['totalPago'],
        'fecha_simulacion' => (new DateTimeImmutable())->format('d/m/Y'),
    ];

    $latte = \App\Fabricas\FabricaLatte::obtenerInstancia();
    $html = $latte->renderToString(__DIR__ . '/../plantillas/prestamos-pdf-simulador-resultados.latte', $datos);

    $pdf = new Dompdf();
    $pdf->loadHtml($html);
    $pdf->setPaper('Letter');
    $options = $pdf->getOptions();
    $options->setIsRemoteEnabled(true);
    $pdf->setOptions($options);
    $pdf->render();

    $pdf->stream('simulacion-prestamo.pdf', ['Attachment' => true]);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Error al generar el PDF: ' . htmlspecialchars($e->getMessage());
    exit;
}

