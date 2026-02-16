<?php
function calcularInteres(int $monto, string $tasa, int $plazo): array
{
    $tasa = floatval($tasa);
    $costoFijo = $monto / $plazo;

    $saldoFinalMes = $monto;

    foreach (range(1, $plazo) as $mes) {
        $interesMes = ($saldoFinalMes * $tasa) / 100;
        $pagoMes = $costoFijo + $interesMes;
        $saldoFinalMes -= $costoFijo;

        $resultado[] = [
                'mes' => $mes,
                'costo_fijo' => round($costoFijo, 2),
                'interes_mes' => round($interesMes, 2),
                'pago_mes' => round($pagoMes, 2),
                'saldo_final_mes' => round(max($saldoFinalMes, 0), 2),
        ];
    }
    return $resultado;
}

function calcularPagoTotal(array $tabla): array
{
    $totalInteres = 0;
    $totalPago = 0;

    foreach ($tabla as $fila) {
        $totalInteres += $fila['interes_mes'];
        $totalPago += $fila['pago_mes'];
    }

    return [
            'total_interes' => round($totalInteres, 2),
            'total_pago' => round($totalPago, 2),
    ];
}