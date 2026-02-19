<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Module\Prestamo\Repository\PrestamoRepository;
use App\Module\Prestamo\Service\SimuladorService;
use App\Shared\Context\Intereses;
use Dompdf\Dompdf;
use Latte\Engine;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$latte = $container->get(Engine::class);
$prestamoRepo = $container->get(PrestamoRepository::class);
$simuladorService = $container->get(SimuladorService::class);

$categoriasTipoIngreso = $prestamoRepo->obtenerCategoriasTipoIngreso();
$simulaciones = [];
$error = null;
$descuentosJson = $_POST["descuentos_json"] ?? $_GET["descuentos_json"] ?? null;
$montoTotal = 0;
$interesTotal = 0;
$pagoTotal = 0;

if ($descuentosJson) {
    try {
        $descuentos = json_decode($descuentosJson, true);
        
        if (is_array($descuentos) && !empty($descuentos)) {
            
            foreach ($descuentos as $descuento) {
                $monto = floatval($descuento['monto'] ?? 0);
                $tipoId = intval($descuento['tipoId'] ?? 0);
                $cantidad = intval($descuento['cantidad'] ?? 1);
                
                if ($monto <= 0) {
                    continue;
                }
                
                $categoria = array_filter($categoriasTipoIngreso, fn($c) => $c->id === $tipoId);
                $categoria = reset($categoria);
                
                if (!$categoria) {
                    continue;
                }
                
                $plazo = $cantidad;
                if ($categoria->esPeriodico) {
                    if ($categoria->frecuenciaDias === 15) {
                        $plazo = $cantidad;
                    } else {
                        $plazo = (int)ceil($cantidad * 2);
                    }
                } else {
                    $plazo = 12;
                }
                
                $tipoInteres = Intereses::AHORRADOR_NO_AGREMIADO;
                
                try {
                    $resultado = $simuladorService->simular(
                        $monto,
                        $tipoInteres,
                        $plazo,
                        $categoria->esPeriodico,  // true = interés simple, false = interés compuesto
                        new \DateTimeImmutable()
                    );
                    
                    $corrida_con_numero = array_map(function($pago, $num) {
                        return [
                            'numero' => $num + 1,
                            'dto' => $pago,
                        ];
                    }, $resultado['corrida'], array_keys($resultado['corrida']));
                    
                    $simulaciones[] = [
                        'categoria_nombre' => $categoria->nombre,
                        'monto' => $monto,
                        'plazo' => $plazo,
                        'interes_tipo' => $tipoInteres->valor(),
                        'corrida' => $resultado['corrida'],
                        'corrida_con_numero' => $corrida_con_numero,
                        'totales' => $resultado['totales'],
                    ];
                    
                    $montoTotal += $resultado['totales']['totalCapital'];
                    $interesTotal += $resultado['totales']['totalInteres'];
                    $pagoTotal += $resultado['totales']['totalPago'];
                } catch (Exception $e) {
                    error_log("Error simulando: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error procesando descuentos JSON: " . $e->getMessage());
    }
}

if (empty($simulaciones)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'No hay simulaciones para generar el PDF.';
    exit;
}

// Renderizar HTML usando la plantilla .latte
$html = $latte->renderToString('./pdf-simulados.latte', [
    "simulaciones" => $simulaciones,
    "resumen" => [
        'montoTotal' => $montoTotal,
        'interesTotal' => $interesTotal,
        'pagoTotal' => $pagoTotal,
    ],
    "fecha_simulacion" => (new \DateTimeImmutable())->format('d/m/Y H:i'),
]);

// Generar PDF
$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();

$pdf->stream('simulacion-prestamos.pdf', ['Attachment' => true]);
