<?php

namespace App\Module\Prestamo\Service;

use App\Module\Prestamo\DTO\AmortizacionCorridaDTO;
use App\Module\Prestamo\Entity\UnidadEnum;
use DateTimeImmutable;

class CalculadoraCompuesto
{
    /**
     * Genera una corrida con interés compuesto usando cuotas periódicas constantes (anualidad).
     *
     * @param float $capital
     * @param float $tasa Tasa anual (ej. 3 o 0.03)
     * @param int $periodos
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

        $alinearCalendario = $options['alinear'] ?? false;
        $fechaPrimerPagoOverride = $options['fechaPrimerPago'] ?? null;
        $dayCount = $options['dayCount'] ?? 'actual/365';

        if ($tasa > 1) {
            $tasa = $tasa / 100.0;
        }

        switch ($unidad) {
            case UnidadEnum::Mes:
                $r = $tasa / 12.0;
                break;
            case UnidadEnum::Quincena:
                $r = $tasa / 24.0;
                break;
            case UnidadEnum::Semana:
                $r = $tasa / 52.0;
                break;
            case UnidadEnum::Dia:
                $r = $tasa / 365.0;
                break;
            default:
                $r = $tasa;
        }

        // determinar primer pago alineado si corresponde
        $hasPartialFirst = false;
        $firstPaymentDate = null;
        if ($fechaPrimerPagoOverride instanceof DateTimeImmutable) {
            $firstPaymentDate = $fechaPrimerPagoOverride;
        } elseif ($alinearCalendario) {
            switch ($unidad) {
                case UnidadEnum::Mes:
                    $firstPaymentDate = new DateTimeImmutable($fechaInicio->format('Y-m-t'));
                    break;
                case UnidadEnum::Quincena:
                    $day = (int)$fechaInicio->format('d');
                    if ($day <= 15) {
                        $firstPaymentDate = DateTimeImmutable::createFromFormat('Y-m-d', $fechaInicio->format('Y-m-15'));
                    } else {
                        $firstPaymentDate = new DateTimeImmutable($fechaInicio->format('Y-m-t'));
                    }
                    break;
                default:
                    $firstPaymentDate = null;
            }
        }

        $diasPrimerPeriodo = 0;
        if ($firstPaymentDate instanceof DateTimeImmutable && $firstPaymentDate > $fechaInicio) {
            $diasPrimerPeriodo = (int)$fechaInicio->diff($firstPaymentDate)->days;
            $hasPartialFirst = $diasPrimerPeriodo > 0;
        }

        $corrida = [];
        $saldo = round($capital, 2);

        // cuota periódica constante (anualidad)
        if ($r > 0.0) {
            $den = 1 - pow(1 + $r, -$periodos);
            $cuota = $den > 0 ? round($capital * $r / $den, 2) : round($capital / $periodos, 2);
        } else {
            $cuota = round($capital / $periodos, 2);
        }

        // base para fechas: si hay primer periodo parcial usamos esa fecha como referencia
        $baseDate = $hasPartialFirst ? $firstPaymentDate : $fechaInicio;

        for ($i = 1; $i <= $periodos; $i++) {
            // interés del periodo — primer periodo puede ser parcial
            if ($i === 1 && $hasPartialFirst) {
                if ($dayCount === 'actual/360') {
                    $interesPeriodo = round($saldo * $tasa * ($diasPrimerPeriodo / 360.0), 2);
                } else {
                    // default actual/365
                    $interesPeriodo = round($saldo * $tasa * ($diasPrimerPeriodo / 365.0), 2);
                }
            } else {
                $interesPeriodo = round($saldo * $r, 2);
            }

            $capitalSoluto = round($cuota - $interesPeriodo, 2);
            if ($r === 0.0) {
                $capitalSoluto = round($capital / $periodos, 2);
            }

            // corregir en el último periodo
            if ($i === $periodos) {
                $capitalSoluto = round($saldo, 2);
                $cuota = round($capitalSoluto + $interesPeriodo, 2);
            }

            $saldo = round(max($saldo - $capitalSoluto, 0.0), 2);

            // calcular fecha de pago
            $periodIndex = $hasPartialFirst ? ($i - 1) : $i;
            switch ($unidad) {
                case UnidadEnum::Mes:
                    $fechaPago = $baseDate->add(new \DateInterval("P{$periodIndex}M"));
                    break;
                case UnidadEnum::Quincena:
                    $fechaPago = $baseDate->add(new \DateInterval('P' . (15 * $periodIndex) . 'D'));
                    break;
                case UnidadEnum::Semana:
                    $fechaPago = $baseDate->add(new \DateInterval('P' . (7 * $periodIndex) . 'D'));
                    break;
                case UnidadEnum::Dia:
                default:
                    $fechaPago = $baseDate->add(new \DateInterval('P' . $periodIndex . 'D'));
                    break;
            }

            $corrida[] = new AmortizacionCorridaDTO(
                $fechaPago,
                $capitalSoluto,
                $interesPeriodo,
                $cuota,
                $saldo,
            );
        }

        return $corrida;
    }
}
