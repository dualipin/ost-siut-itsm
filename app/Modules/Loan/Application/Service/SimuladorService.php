<?php

namespace App\Module\Prestamo\Service;

use App\Module\Prestamo\DTO\AmortizacionCorridaDTO;
use App\Module\Prestamo\Enum\UnidadEnum;
use DateTimeImmutable;

/**
 * Servicio de Simulador de Préstamos.
 * Orquesta el cálculo de simulaciones eligiendo entre:
 * - Préstamos periódicos: Método alemán (saldo decreciente) con interés compuesto
 * - Préstamos no periódicos: Interés compuesto con cuota constante
 */
final readonly class SimuladorService
{
    public function __construct(
        private CalculadoraCompuesto $calculadoraCompuesto,
        private CalculadoraIntereSimple $calculadoraSimple,
    ) {}

    /**
     * Simula un préstamo con los parámetros proporcionados.
     *
     * @param float $monto Monto del préstamo
     * @param Intereses $tipoInteres Tipo de interés a aplicar
     * @param int $periodos Número de períodos (quincenas)
     * @param bool $esPeriodico ¿Es un beneficio periódico? (true = método alemán, false = anualidad)
     * @param DateTimeImmutable|null $fechaInicio Fecha de inicio (por defecto hoy)
     * @param array $options Opciones adicionales (alinear, fechaPrimerPago, dayCount)
     *
     * @return array{corrida: AmortizacionCorridaDTO[], totales: array{totalInteres: float, totalCapital: float, totalPago: float}}
     */
    public function simular(
        float $monto,
        Intereses $tipoInteres,
        int $periodos,
        bool $esPeriodico = false,
        ?DateTimeImmutable $fechaInicio = null,
        array $options = [],
    ): array {
        // Validaciones
        if ($monto <= 0) {
            throw new \InvalidArgumentException(
                "El monto debe ser mayor a cero.",
            );
        }

        if ($periodos <= 0) {
            throw new \InvalidArgumentException(
                "El número de períodos debe ser mayor a cero.",
            );
        }

        $fechaInicio = $fechaInicio ?? new DateTimeImmutable();

        // Elegir calculadora según si es periódico o no
        $calculadora = $esPeriodico
            ? $this->calculadoraSimple
            : $this->calculadoraCompuesto;

        // Generar corrida usando la calculadora seleccionada
        $corrida = $calculadora->generarCorrida(
            $monto,
            $tipoInteres->valor(),
            $periodos,
            $fechaInicio,
            UnidadEnum::Quincena,
            array_merge($options, ["alinear" => true]),
        );

        // Calcular totales
        $totales = $this->calcularTotales($corrida, $monto);

        return [
            "corrida" => $corrida,
            "totales" => $totales,
        ];
    }

    /**
     * Calcula los totales de interés y pago.
     *
     * @param AmortizacionCorridaDTO[] $corrida
     * @param float $montoOriginal
     *
     * @return array{totalInteres: float, totalCapital: float, totalPago: float}
     */
    private function calcularTotales(
        array $corrida,
        float $montoOriginal,
    ): array {
        $totalInteres = 0.0;
        $totalPago = 0.0;

        foreach ($corrida as $fila) {
            $totalInteres += (float) $fila->interes;
            $totalPago += (float) $fila->valor;
        }

        return [
            "totalInteres" => round($totalInteres, 2),
            "totalCapital" => round($montoOriginal, 2),
            "totalPago" => round($totalPago, 2),
        ];
    }

    /**
     * Formatea una corrida de amortización para presentación.
     *
     * @param AmortizacionCorridaDTO[] $corrida
     *
     * @return array[]
     */
    public function formatearCorrida(array $corrida): array
    {
        return array_map(
            fn(AmortizacionCorridaDTO $dto) => [
                "numero_pago" => 0, // se asignará en el llamador
                "fecha_pago" => $dto->fechaPago->format("d/m/Y"),
                "capital_soluto" => number_format(
                    $dto->capitalSoluto,
                    2,
                    ",",
                    ".",
                ),
                "interes" => number_format($dto->interes, 2, ",", "."),
                "cuota" => number_format($dto->valor, 2, ",", "."),
                "saldo" => number_format($dto->saldo, 2, ",", "."),
                "capital_soluto_value" => (float) $dto->capitalSoluto,
                "interes_value" => (float) $dto->interes,
                "cuota_value" => (float) $dto->valor,
                "saldo_value" => (float) $dto->saldo,
            ],
            $corrida,
        );
    }
}
