<?php

declare(strict_types=1);

use App\Manejadores\SesionProtegida;
use App\Module\Prestamo\Service\SimuladorService;
use App\Shared\Context\Intereses;
use App\Bootstrap;
use DateTimeImmutable;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();

$container = Bootstrap::buildContainer();
$simuladorService = $container->get(SimuladorService::class);
$latte = \App\Fabricas\FabricaLatte::obtenerInstancia();

$error = null;
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
        $plazo = isset($_POST['plazo']) ? intval($_POST['plazo']) : 0;
        $tasaTipo = $_POST['tasa'] ?? 'ahorrador_no_agremiado';

        // Validaciones
        if ($monto <= 0) {
            $error = 'El monto debe ser mayor a cero.';
        } elseif ($plazo <= 0) {
            $error = 'El plazo debe ser mayor a cero.';
        } else {
            // Seleccionar tipo de interés
            $interes = Intereses::AHORRADOR_NO_AGREMIADO;
            if ($tasaTipo === 'ahorrador_agremiado') {
                $interes = Intereses::AHORRADOR_AGREMIADO;
            } elseif ($tasaTipo === 'no_ahorrador_agremiado') {
                $interes = Intereses::NO_AHORRADOR_AGREMIADO;
            }

            // Generar simulación
            $resultado = $simuladorService->simular(
                $monto,
                $interes,
                $plazo,
                new DateTimeImmutable()
            );
        }
    } catch (Exception $e) {
        $error = 'Error al procesar la simulación: ' . $e->getMessage();
    }
}

$datos = [
    'error' => $error,
    'resultado' => $resultado,
    'tipos_interes' => [
        'ahorrador_no_agremiado' => 'Ahorrador No Agremiado (3%)',
        'ahorrador_agremiado' => 'Ahorrador Agremiado (2%)',
        'no_ahorrador_agremiado' => 'No Ahorrador Agremiado (2.5%)',
    ],
];

$latte->render(__DIR__ . '/../plantillas/prestamos-simulador.latte', $datos);
