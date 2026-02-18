<?php

namespace App\Module\Prestamo\Service;

use App\Module\Prestamo\DTO\AmortizacionCorridaDTO;
use App\Module\Prestamo\Entity\UnidadEnum;
use App\Module\Prestamo\Service\CalculadoraPrestamoInterface;
use DateTimeImmutable;

class CalculadoraSimpleAleman
{
    /**
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
        if ($periodos <= 0) {
            return [];
        }

        // opciones
        $alinearCalendario = $options['alinear'] ?? false; // si true, alinea primer pago a fin de periodo (mes/quincena)
        $fechaPrimerPagoOverride = $options['fechaPrimerPago'] ?? null; // DateTimeImmutable opcional
        $dayCount = $options['dayCount'] ?? 'actual/365'; // 'actual/365'|'actual/360'|'period'

        // Normalizar tasa si viene como porcentaje (p.ej. 3 => 0.03)
        if ($tasa > 1) {
            $tasa = $tasa / 100.0;
        }

        // tasa por periodo según la unidad (valor usado para periodos completos)
        switch ($unidad) {
            case UnidadEnum::Mes:
                $tasaPeriodo = $tasa / 12.0;
                break;
            case UnidadEnum::Quincena:
                $tasaPeriodo = $tasa / 24.0;
                break;
            case UnidadEnum::Semana:
                $tasaPeriodo = $tasa / 52.0;
                break;
            case UnidadEnum::Dia:
                $tasaPeriodo = $tasa / 365.0;
                break;
            default:
                $tasaPeriodo = $tasa;
        }

        // --- calcular fecha del primer pago si se pide alinear a calendario ---
        $hasPartialFirst = false;
        $firstPaymentDate = null;
        if ($fechaPrimerPagoOverride instanceof DateTimeImmutable) {
            $firstPaymentDate = $fechaPrimerPagoOverride;
        } elseif ($alinearCalendario) {
            switch ($unidad) {
                case UnidadEnum::Mes:
                    // primer pago = último día del mes de la fecha de desembolso
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
                    // para semanas/días mantenemos el comportamiento relativo al desembolso
                    $firstPaymentDate = null;
            }
        }

        // si se determinó una fecha de primer pago por calendario, calcular días del primer periodo
        $diasPrimerPeriodo = 0;
        if ($firstPaymentDate instanceof DateTimeImmutable && $firstPaymentDate > $fechaInicio) {
            $diasPrimerPeriodo = (int)$fechaInicio->diff($firstPaymentDate)->days;
            $hasPartialFirst = $diasPrimerPeriodo > 0;
        }

        $corrida = [];
        $saldo = round($capital, 2);
        $capitalSolutoBase = $capital / $periodos; // amortización de capital constante (método alemán)

        // base para fechas: si hay primer pago alineado usamos esa fecha como referencia para pagos posteriores
        $baseDate = $hasPartialFirst ? $firstPaymentDate : $fechaInicio;

        for ($i = 1; $i <= $periodos; $i++) {
            // capital soluto constante (ajustar último periodo)
            $capitalSoluto = ($i === $periodos) ? round($saldo, 2) : round($capitalSolutoBase, 2);

            // calcular interés: primer periodo puede ser parcial (días) si alineado a calendario
            if ($i === 1 && $hasPartialFirst) {
                // convención de día: actual/365 o actual/360 o proporcional al periodo
                if ($dayCount === 'actual/360') {
                    $interesPeriodo = round($saldo * $tasa * ($diasPrimerPeriodo / 360.0), 2);
                } elseif ($dayCount === 'period') {
                    // fracción del periodo usando tasaPeriodo
                    $longitudPeriodo = $unidad === UnidadEnum::Quincena ? 15 : 30; // aproximación para mes
                    $interesPeriodo = round($saldo * $tasaPeriodo * ($diasPrimerPeriodo / $longitudPeriodo), 2);
                } else {
                    // default actual/365
                    $interesPeriodo = round($saldo * $tasa * ($diasPrimerPeriodo / 365.0), 2);
                }
            } else {
                // interés para periodos completos
                $interesPeriodo = round($saldo * $tasaPeriodo, 2);
            }

            $pagoPeriodo = round($capitalSoluto + $interesPeriodo, 2);

            // calcular fecha de pago — si hubo primer periodo parcial usamos baseDate + (i-1)*period,
            // si no hubo parcial usamos fechaInicio + i*period
            if ($hasPartialFirst) {
                $periodIndex = $i - 1; // 0-based offset desde firstPaymentDate
            } else {
                $periodIndex = $i; // offset desde fechaInicio
            }

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

            // reducir saldo después de calcular interés y pago
            $saldo = round(max($saldo - $capitalSoluto, 0.0), 2);

            $corrida[] = new AmortizacionCorridaDTO(
                $fechaPago,
                number_format($capitalSoluto, 2, '.', ''),
                number_format($interesPeriodo, 2, '.', ''),
                number_format($pagoPeriodo, 2, '.', ''),
                number_format($saldo, 2, '.', ''),
            );
        }

        return $corrida;
    }
}
