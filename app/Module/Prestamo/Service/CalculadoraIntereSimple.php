<?php

namespace App\Module\Prestamo\Service;

use App\Module\Prestamo\DTO\AmortizacionCorridaDTO;
use App\Module\Prestamo\Enum\UnidadEnum;
use DateTimeImmutable;

class CalculadoraIntereSimple
{
    /**
     * Genera una corrida con el método alemán (saldo decreciente) e interés compuesto.
     * - Capital de amortización FIJO cada período
     * - Interés COMPUESTO sobre saldo decreciente
     * - Resultado: Cuotas decrecientes
     *
     * @param float $capital Monto del préstamo
     * @param float $tasa Tasa anual (ej. 3 o 0.03)
     * @param int $periodos Número de períodos (quincenas)
     * @param DateTimeImmutable $fechaInicio
     * @param UnidadEnum $unidad
     * @return AmortizacionCorridaDTO[]
     */
    public function generarCorrida(
        float $capital,
        float $tasa,
        int $periodos,
        DateTimeImmutable $fechaInicio,
        UnidadEnum $unidad = UnidadEnum::Quincena,
        array $options = [],
    ): array {
        if ($periodos <= 0 || $capital <= 0) {
            return [];
        }

        $alinearCalendario = $options["alinear"] ?? false;
        $fechaPrimerPagoOverride = $options["fechaPrimerPago"] ?? null;

        if ($tasa > 1) {
            $tasa = $tasa / 100.0;
        }

        // Determinar la tasa por período
        // La tasa se aplica directamente (no se divide por frecuencia)
        $tasaPeriodo = $tasa > 1 ? $tasa / 100.0 : $tasa;

        // Determinar primer pago alineado si corresponde
        $firstPaymentDate = null;
        if ($fechaPrimerPagoOverride instanceof DateTimeImmutable) {
            $firstPaymentDate = $fechaPrimerPagoOverride;
        } elseif ($alinearCalendario) {
            switch ($unidad) {
                case UnidadEnum::Mes:
                    $firstPaymentDate = new DateTimeImmutable(
                        $fechaInicio->format("Y-m-t"),
                    );
                    break;
                case UnidadEnum::Quincena:
                    $day = (int) $fechaInicio->format("d");
                    if ($day <= 15) {
                        $firstPaymentDate = DateTimeImmutable::createFromFormat(
                            "Y-m-d",
                            $fechaInicio->format("Y-m-15"),
                        );
                    } else {
                        $firstPaymentDate = new DateTimeImmutable(
                            $fechaInicio->format("Y-m-t"),
                        );
                    }
                    break;
                default:
                    $firstPaymentDate = null;
            }
        }

        $baseDate = $firstPaymentDate ?? $fechaInicio;

        $corrida = [];
        $saldo = round($capital, 2);

        // Capital fijo a amortizar por período
        $capitalFijo = round($capital / $periodos, 2);

        for ($i = 1; $i <= $periodos; $i++) {
            // Interés COMPUESTO sobre saldo decreciente
            $interes = round($saldo * $tasaPeriodo, 2);

            // Capital soluto es fijo
            $capitalSoluto = $capitalFijo;

            // En el último período, ajustar capital para que cuadre exactamente
            if ($i === $periodos) {
                $capitalSoluto = round($saldo, 2);
            }

            // Cuota total = Capital fijo + Interés sobre saldo
            $cuota = round($capitalSoluto + $interes, 2);

            $saldo = round(max($saldo - $capitalSoluto, 0.0), 2);

            // Calcular fecha de pago
            switch ($unidad) {
                case UnidadEnum::Mes:
                    $fechaPago = $baseDate->add(new \DateInterval("P{$i}M"));
                    break;
                case UnidadEnum::Quincena:
                    $fechaPago = $baseDate->add(
                        new \DateInterval("P" . 15 * $i . "D"),
                    );
                    break;
                case UnidadEnum::Semana:
                    $fechaPago = $baseDate->add(
                        new \DateInterval("P" . 7 * $i . "D"),
                    );
                    break;
                case UnidadEnum::Dia:
                default:
                    $fechaPago = $baseDate->add(
                        new \DateInterval("P" . $i . "D"),
                    );
                    break;
            }

            $corrida[] = new AmortizacionCorridaDTO(
                $fechaPago,
                $capitalSoluto,
                $interes,
                $cuota,
                $saldo,
            );
        }

        return $corrida;
    }
}
