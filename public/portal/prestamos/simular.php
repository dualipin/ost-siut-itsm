<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Prestamo\Repository\PrestamoRepository;
use App\Module\Prestamo\Service\SimuladorService;
use App\Shared\Context\Intereses;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$prestamoRepo = $container->get(PrestamoRepository::class);
$simuladorService = $container->get(SimuladorService::class);

$categoriasTipoIngreso = $prestamoRepo->obtenerCategoriasTipoIngreso();
$simulaciones = [];
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Obtener datos del formulario
        $descuentosJson = $_POST["descuentos_json"] ?? null;
        
        if (!$descuentosJson) {
            $error = "No hay descuentos para simular.";
        } else {
            $descuentos = json_decode($descuentosJson, true);
            
            if (!is_array($descuentos) || empty($descuentos)) {
                $error = "Datos inválidos en la simulación.";
            } else {
                $montoTotal = 0;
                $interesTotal = 0;
                $pagoTotal = 0;
                
                // Procesar cada descuento/tipo de pago
                foreach ($descuentos as $index => $descuento) {
                    $monto = floatval($descuento['monto'] ?? 0);
                    $tipoId = intval($descuento['tipoId'] ?? 0);
                    $cantidad = intval($descuento['cantidad'] ?? 1);
                    
                    // Validar
                    if ($monto <= 0) {
                        continue; // Saltar montos inválidos
                    }
                    
                    // Obtener categoría para determinar la unidad
                    $categoria = array_filter($categoriasTipoIngreso, fn($c) => $c->id === $tipoId);
                    $categoria = reset($categoria);
                    
                    if (!$categoria) {
                        continue;
                    }
                    
                    // Convertir cantidad a quincenas según la unidad
                    $plazo = $cantidad;
                    if ($categoria->esPeriodico) {
                        if ($categoria->frecuenciaDias === 15) {
                            // Ya está en quincenas
                            $plazo = $cantidad;
                        } else {
                            // Convertir meses a quincenas (1 mes ≈ 2 quincenas)
                            $plazo = (int)ceil($cantidad * 2);
                        }
                    } else {
                        // Para no periódicos, usar un plazo por defecto de 12 quincenas (6 meses)
                        $plazo = 12;
                    }
                    
                    // Usar interés compuesto
                    $tipoInteres = Intereses::AHORRADOR_NO_AGREMIADO;
                    
                    try {
                        $resultado = $simuladorService->simular(
                            $monto,
                            $tipoInteres,
                            $plazo,
                            new DateTimeImmutable()
                        );
                        
                        $simulaciones[] = [
                            'categoria_nombre' => $categoria->nombre,
                            'monto' => $monto,
                            'plazo' => $plazo,
                            'interes_tipo' => $tipoInteres->value,
                            'corrida' => $resultado['corrida'],
                            'totales' => $resultado['totales'],
                        ];
                        
                        $montoTotal += $resultado['totales']['totalCapital'];
                        $interesTotal += $resultado['totales']['totalInteres'];
                        $pagoTotal += $resultado['totales']['totalPago'];
                    } catch (Exception $e) {
                        $error = "Error al simular {$categoria->nombre}: " . $e->getMessage();
                        break;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error al procesar la simulación: " . $e->getMessage();
    }
}

// Procesar simulaciones para la plantilla
$simulacionesFormateadas = array_map(function($sim, $index) {
    $corrida_con_numero = array_map(function($pago, $num) {
        return [
            'numero' => $num + 1,
            'dto' => $pago,
        ];
    }, $sim['corrida'], array_keys($sim['corrida']));
    
    return array_merge($sim, ['corrida_con_numero' => $corrida_con_numero]);
}, $simulaciones, array_keys($simulaciones));

$renderer->render("./simular.latte", [
    "categoriasTipoIngreso" => $categoriasTipoIngreso,
    "simulaciones" => $simulacionesFormateadas,
    "error" => $error,
    "resumen" => [
        'montoTotal' => $montoTotal ?? 0,
        'interesTotal' => $interesTotal ?? 0,
        'pagoTotal' => $pagoTotal ?? 0,
    ],
    "descuentos_json" => $_POST["descuentos_json"] ?? "[]",
]);
