<?php

namespace App\Module\Prestamo\Util;

use App\Module\Prestamo\DTO\AmortizacionCorridaDTO;
use App\Module\Prestamo\Enum\UnidadEnum;
use DateTime;
use DateTimeImmutable;

final class CalculadoraInteresSimple
{
    private function __construct() {}

    public static function calcular(
        float $monto,
        float $porcentaje,
        int $periodos,
        DateTimeImmutable $fechaInicio,
        int $dia,
        int $frecuenciaDias,
    ) {
        $amortizacionFija = $monto / $periodos;

        //        for ($i = 1; $i <= $periodos; $i++) {
        //            $interes = ($monto - $amortizacionFija * ($i - 1)) * $porcentaje;
        //            $cuota = $amortizacionFija + $interes;
        //            echo "Periodo {$i}: Cuota = " . round($cuota, 2) . "\n";
        //        }
    }

    public static function diasParaProximoCorte(DateTime $fechaActual = null)
    {
        // 1. Usamos 'today' para que la hora sea 00:00:00 obligatoriamente
        $hoy = $fechaActual ?? new DateTime("today");

        $diaActual = (int) $hoy->format("j");
        $mesActual = (int) $hoy->format("n");
        $anioActual = (int) $hoy->format("Y");

        // 2. Determinamos la fecha de corte
        if ($diaActual < 15) {
            $proximoCorte = new DateTime("$anioActual-$mesActual-15");
        } else {
            $ultimoDiaMes = (int) $hoy->format("t");
            $diaDestino = $ultimoDiaMes < 30 ? $ultimoDiaMes : 30;

            if ($diaActual >= $diaDestino) {
                $proximoCorte = new DateTime("first day of next month");
                $proximoCorte->setDate(
                    (int) $proximoCorte->format("Y"),
                    (int) $proximoCorte->format("m"),
                    15,
                );
            } else {
                $proximoCorte = new DateTime(
                    "$anioActual-$mesActual-$diaDestino",
                );
            }
        }

        // 3. Calculamos la diferencia
        $diferencia = $hoy->diff($proximoCorte);
        $diasResultantes = $diferencia->days;

        // 4. AJUSTE DE FACTURACIÓN:
        // Si la diferencia es mayor a 0, sumamos 1 para que sea inclusivo (del 1 al 15 = 14 días de plazo)
        if ($diasResultantes > 0) {
            $diasResultantes += 1;
        }

        return [
            "dias" => $diasResultantes,
            "fecha_corte" => $proximoCorte->format("Y-m-d"),
        ];
    }
}
