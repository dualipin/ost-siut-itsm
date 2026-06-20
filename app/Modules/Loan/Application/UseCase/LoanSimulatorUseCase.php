<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\DTO\LoanSimulationDTO;
use App\Modules\Loan\Domain\DTO\LoanSimulationDiscountDTO;
use App\Modules\Loan\Domain\Entity\IncomeType;
use DateInterval;
use DateTime;
use DateTimeImmutable;

final readonly class LoanSimulatorUseCase
{
    private const int FIRST_PAYMENT_TOLERANCE_DAYS = 15;

    public function __construct(private ListIncomeTypesUseCase $listIncomeTypesUseCase) {}

    /**
     * @param LoanSimulationDTO $dto
     * @return array<string, mixed>
     */
    public function execute(LoanSimulationDTO $dto): array
    {
        $categorias = $this->listIncomeTypesUseCase->execute();

        $categoriasMap = [];
        foreach ($categorias as $cat) {
            $categoriasMap[$cat->id] = $cat;
        }

        $montoPrestamo = max(0.0, $dto->montoPrestamo);
        $fechaOtorgamiento = $dto->fechaOtorgamiento;
        $mesesPagar = max(0, $dto->mesesPagar);
        $diasAdicionales = max(0, $dto->diasAdicionales);
        $tasaInteresMensual = $dto->tasaInteres;

        $fechaBase = DateTimeImmutable::createFromFormat('Y-m-d', $fechaOtorgamiento) ?: new DateTimeImmutable();
        $plazoDias = ($mesesPagar * 30) + $diasAdicionales;

        $resumenAnual = [];
        $formasPago = [];
        $prestacionesNoPeriodicas = [];
        $corridaPrestaciones = [];
        $acumuladoPrestaciones = [];
        $prestacionesParaCorrida = [];

        foreach ($dto->descuentos as $desc) {
            $tipoId = $desc->tipoId;
            $monto = $desc->monto;
            if ($tipoId <= 0 || $monto <= 0) {
                continue;
            }

            if (!isset($categoriasMap[$tipoId])) {
                continue;
            }
            /** @var IncomeType $cat */
            $cat = $categoriasMap[$tipoId];
            $nombre = $cat->name;

            if ($cat->isPeriodic) {
                $frecuenciaDias = max(1, (int)($cat->frequencyDays ?? 15));
                $cantidadSolicitada = max(1, $desc->cantidad);
                $opcionesFechas = $this->buildPeriodicOptions($fechaBase, $cat);
                if ($opcionesFechas === []) {
                    continue;
                }

                $fechaSeleccionada = !empty($desc->fechaPago)
                    ? $desc->fechaPago
                    : $opcionesFechas[0];

                $indiceSeleccionado = array_search($fechaSeleccionada, $opcionesFechas, true);
                if ($indiceSeleccionado === false) {
                    $indiceSeleccionado = min($cantidadSolicitada - 1, count($opcionesFechas) - 1);
                }

                $cantidad = $indiceSeleccionado + 1;
                $opcionesSeleccionadas = array_slice($opcionesFechas, 0, $cantidad);
                $fechaUltimaStr = $opcionesSeleccionadas[count($opcionesSeleccionadas) - 1];
                $fechaUltimoPeriodo = DateTimeImmutable::createFromFormat('Y-m-d', $fechaUltimaStr) ?: $fechaBase;

                $diasHastaPrestacion = max(0, (int)$fechaBase->diff($fechaUltimoPeriodo)->days);
                $plazoDias = max($plazoDias, $diasHastaPrestacion);

                $formasPago[] = [
                    'tipo' => 'periodico',
                    'nombre' => $nombre,
                    'monto' => $monto,
                    'frecuenciaDias' => $frecuenciaDias,
                    'cantidad' => $cantidad,
                    'diaTentativo' => (int)$fechaUltimoPeriodo->format('d'),
                ];

                $prestacionesParaCorrida[] = [
                    'nombre' => $nombre,
                    'tipo' => 'periodico',
                    'monto' => $monto,
                    'frecuenciaDias' => $frecuenciaDias,
                    'cantidad' => $cantidad,
                    'fechas' => $opcionesSeleccionadas,
                ];

                $acumuladoPrestaciones[$nombre] = ($acumuladoPrestaciones[$nombre] ?? 0.0) + $monto;

                $corridaPrestaciones[] = [
                    'prestacion' => $nombre,
                    'tipo' => 'periodico',
                    'periodo' => $cantidad . ' periodo(s)',
                    'fecha' => $fechaUltimaStr,
                    'monto' => $monto,
                    'acumulado' => $acumuladoPrestaciones[$nombre],
                ];
                continue;
            }

            // No periódico
            $mesTentativo = max(1, (int)($cat->paymentMonth ?? 12));
            $diaTentativo = max(1, (int)($cat->paymentDay ?? 1));
            $fechaPrestacion = $this->resolvePagoDate($fechaBase, $mesTentativo, $diaTentativo);
            $diasHastaPrestacion = max(0, (int)$fechaBase->diff($fechaPrestacion)->days);
            $plazoDias = max($plazoDias, $diasHastaPrestacion);

            $prestacionesNoPeriodicas[] = [
                'nombre' => $nombre,
                'fecha' => $fechaPrestacion,
                'monto' => $monto,
            ];

            if (!isset($resumenAnual[$nombre])) {
                $resumenAnual[$nombre] = 0;
            }
            $resumenAnual[$nombre] += $monto;

            $formasPago[] = [
                'tipo' => 'no_periodico',
                'nombre' => $nombre,
                'monto' => $monto,
                'fechaPago' => $fechaPrestacion->format('Y-m-d'),
            ];

            $prestacionesParaCorrida[] = [
                'nombre' => $nombre,
                'tipo' => 'no_periodico',
                'monto' => $monto,
                'fecha' => $fechaPrestacion->format('Y-m-d'),
            ];

            $acumuladoPrestaciones[$nombre] = ($acumuladoPrestaciones[$nombre] ?? 0.0) + $monto;
            $corridaPrestaciones[] = [
                'prestacion' => $nombre,
                'tipo' => 'no_periodico',
                'periodo' => '1/1',
                'fecha' => $fechaPrestacion->format('Y-m-d'),
                'monto' => $monto,
                'acumulado' => $acumuladoPrestaciones[$nombre],
            ];
        }

        usort(
            $corridaPrestaciones,
            static fn (array $a, array $b): int => strcmp((string)$a['fecha'], (string)$b['fecha'])
        );

        if ($plazoDias <= 0) {
            $plazoDias = ($mesesPagar * 30) + $diasAdicionales;
        }

        $mesesPagar = intdiv($plazoDias, 30);
        $diasAdicionales = $plazoDias % 30;

        $tasaQuincenal = ($tasaInteresMensual / 100) / 2;
        $tasaDiaria = ($tasaInteresMensual / 100) / 30;

        $numQuincenas = max(1, ($mesesPagar * 2) + (int)ceil($diasAdicionales / 15));
        $capitalFijo = $numQuincenas > 0 ? $montoPrestamo / $numQuincenas : 0;
        $saldo = $montoPrestamo;

        $corrida = [];
        $fechaReferenciaPrimerPago = $fechaBase->add(new DateInterval('P' . self::FIRST_PAYMENT_TOLERANCE_DAYS . 'D'));
        $primerPago = $this->resolveNextFortnightDate($fechaReferenciaPrimerPago);
        $diasTranscurridosPrimerPago = max(0, (int)$fechaBase->diff($primerPago)->days);
        $diasExtraPrimeraQuincena = max(0, $diasTranscurridosPrimerPago - 15);
        $fechaActual = new DateTime($primerPago->format('Y-m-d'));

        $pagoExtraordinarioPorFecha = [];
        foreach ($prestacionesNoPeriodicas as $prestacion) {
            $fechaKey = $prestacion['fecha']->format('Y-m-d');
            if (!isset($pagoExtraordinarioPorFecha[$fechaKey])) {
                $pagoExtraordinarioPorFecha[$fechaKey] = 0.0;
            }
            $pagoExtraordinarioPorFecha[$fechaKey] += (float)$prestacion['monto'];
        }

        for ($i = 1; $i <= $numQuincenas; $i++) {
            $day = (int)$fechaActual->format('d');
            if ($day <= 15) {
                $fechaActual->setDate((int)$fechaActual->format('Y'), (int)$fechaActual->format('m'), 15);
            } else {
                $fechaActual->modify('last day of this month');
            }

            $fechaPagoStr = $fechaActual->format('Y-m-d');
            $interesQuincenal = $saldo * $tasaQuincenal;

            if ($i === 1 && $diasExtraPrimeraQuincena > 0) {
                $interesQuincenal += ($montoPrestamo * $tasaDiaria * $diasExtraPrimeraQuincena);
            }

            $pagoExtraordinario = 0.0;
            if (isset($pagoExtraordinarioPorFecha[$fechaPagoStr])) {
                $pagoExtraordinario = (float)$pagoExtraordinarioPorFecha[$fechaPagoStr];
                unset($pagoExtraordinarioPorFecha[$fechaPagoStr]);
            }

            $capitalAbono = $capitalFijo + $pagoExtraordinario;
            if ($capitalAbono > $saldo) {
                $capitalAbono = $saldo;
            }

            $pagoTotal = $capitalAbono + $interesQuincenal;
            $saldo -= $capitalAbono;
            if ($saldo < 0) {
                $saldo = 0.0;
            }

            $corrida[] = [
                'quincena' => $i,
                'capital' => $capitalAbono,
                'interes' => $interesQuincenal,
                'pago' => $pagoTotal,
                'saldo' => $saldo,
                'fecha' => $fechaPagoStr,
            ];

            if ($saldo <= 0) {
                break;
            }

            if ((int)$fechaActual->format('d') === 15) {
                $fechaActual->modify('last day of this month');
            } else {
                $fechaActual->modify('first day of next month');
                $fechaActual->modify('+14 days');
            }
        }

        $corridasPorTipo = [];
        $interesTotalGlobal = 0.0;
        $pagoTotalGlobal = 0.0;

        foreach ($prestacionesParaCorrida as $prestacion) {
            $esPeriodico = (string)($prestacion['tipo'] ?? '') === 'periodico';
            $corridaTipo = $esPeriodico
                ? $this->buildGermanSimpleSchedule($fechaBase, $prestacion, $tasaInteresMensual)
                : $this->buildCompoundSchedule($fechaBase, $prestacion, $tasaInteresMensual);

            if ($corridaTipo === []) {
                continue;
            }

            $interesTotal = array_reduce($corridaTipo, static fn (float $sum, array $row): float => $sum + (float)$row['interes'], 0.0);
            $pagoTotal = array_reduce($corridaTipo, static fn (float $sum, array $row): float => $sum + (float)$row['pago'], 0.0);

            $interesTotalGlobal += $interesTotal;
            $pagoTotalGlobal += $pagoTotal;

            $corridasPorTipo[] = [
                'prestacion' => (string)($prestacion['nombre'] ?? 'Prestación'),
                'tipo' => (string)($prestacion['tipo'] ?? 'no_periodico'),
                'metodo' => $esPeriodico ? 'Interés simple - Método Alemán' : 'Interés compuesto',
                'montoBase' => (float)($prestacion['monto'] ?? 0.0),
                'corrida' => $corridaTipo,
                'resumen' => [
                    'interesTotal' => $interesTotal,
                    'pagoTotal' => $pagoTotal,
                    'saldoFinal' => (float)$corridaTipo[count($corridaTipo) - 1]['saldo'],
                ],
            ];
        }

        return [
            'montoPrestamo' => $montoPrestamo,
            'mesesPagar' => $mesesPagar,
            'diasAdicionales' => $diasAdicionales,
            'tasaInteresMensual' => $tasaInteresMensual,
            'fechaOtorgamiento' => $fechaOtorgamiento,
            'formasPago' => $formasPago,
            'resumenAnual' => $resumenAnual,
            'corridaPrestaciones' => $corridaPrestaciones,
            'corridasPorTipo' => $corridasPorTipo,
            'resumen' => [
                'montoTotal' => $montoPrestamo,
                'interesTotal' => $interesTotalGlobal,
                'pagoTotal' => $pagoTotalGlobal,
            ],
            'corrida' => $corrida,
        ];
    }

    private function resolvePagoDate(DateTimeImmutable $baseDate, int $month, int $day): DateTimeImmutable
    {
        $year = (int)$baseDate->format('Y');
        $month = min(12, max(1, $month));
        $day = min(31, max(1, $day));

        $date = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $year, $month, $day));
        if (!$date) {
            $date = $baseDate;
        }

        if ($date <= $baseDate) {
            $nextYear = $year + 1;
            $next = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $nextYear, $month, $day));
            if ($next) {
                $date = $next;
            }
        }

        return $date;
    }

    private function buildPeriodicOptions(DateTimeImmutable $inicio, IncomeType $cat): array
    {
        if (empty($cat->isPeriodic)) {
            return [];
        }

        $inicioElegible = $inicio->add(new DateInterval('P' . self::FIRST_PAYMENT_TOLERANCE_DAYS . 'D'));
        $frecuencia = max(1, (int)($cat->frequencyDays ?? 30));
        $diaPago = max(1, (int)($cat->paymentDay ?? 15));
        $fechaMaxima = new DateTimeImmutable($inicioElegible->format('Y') . '-11-15');

        if ($inicioElegible > $fechaMaxima) {
            return [];
        }

        $opciones = [];
        $cursor = $inicioElegible->setDate((int)$inicioElegible->format('Y'), (int)$inicioElegible->format('m'), 1);
        $guard = 0;

        while ($cursor <= $fechaMaxima && $guard < 36) {
            $year = (int)$cursor->format('Y');
            $month = (int)$cursor->format('m');
            $totalDiasMes = (int)$cursor->format('t');

            $fraccionMensual = $frecuencia / $totalDiasMes;
            $vecesEnMes = $fraccionMensual > 0 ? max(1, (int)round(1 / $fraccionMensual)) : 1;
            $diaBase = min(max(1, $diaPago), $totalDiasMes);
            $saltoDias = max(1, (int)round($totalDiasMes / $vecesEnMes));

            for ($i = 0; $i < $vecesEnMes; $i++) {
                $diaCandidato = $diaBase + ($i * $saltoDias);
                if ($diaCandidato > $totalDiasMes) {
                    break;
                }

                $fechaPago = $cursor->setDate($year, $month, $diaCandidato);
                if ($fechaPago < $inicioElegible || $fechaPago > $fechaMaxima) {
                    continue;
                }

                $opciones[] = $fechaPago->format('Y-m-d');
            }

            $cursor = $cursor->modify('first day of next month');
            $guard++;
        }

        return $opciones;
    }

    private function buildGermanSimpleSchedule(DateTimeImmutable $fechaBase, array $prestacion, float $tasaMensual): array
    {
        $monto = max(0.0, (float)($prestacion['monto'] ?? 0.0));
        $cantidad = max(1, (int)($prestacion['cantidad'] ?? 1));
        $frecuenciaDias = max(1, (int)($prestacion['frecuenciaDias'] ?? 15));
        $fechas = is_array($prestacion['fechas'] ?? null) ? $prestacion['fechas'] : [];

        if ($monto <= 0.0) {
            return [];
        }

        $tasaDiaria = ($tasaMensual / 100) / 30;
        $capitalFijo = $monto / $cantidad;
        $saldo = $monto;
        $rows = [];
        
        $fechaAnterior = $fechaBase;

        for ($i = 1; $i <= $cantidad; $i++) {
            $fecha = $fechas[$i - 1] ?? null;
            if (!is_string($fecha) || trim($fecha) === '') {
                $fecha = $fechaBase->modify('+' . ($frecuenciaDias * $i) . ' days')->format('Y-m-d');
            }

            $fechaActual = DateTimeImmutable::createFromFormat('Y-m-d', $fecha) ?: $fechaBase;
            $diasReales = max(1, (int)$fechaAnterior->diff($fechaActual)->days);

            $interes = $saldo * $tasaDiaria * $diasReales;
            $capital = min($capitalFijo, $saldo);
            $pago = $capital + $interes;
            $saldo -= $capital;
            if ($saldo < 0.0) {
                $saldo = 0.0;
            }

            $rows[] = [
                'periodo' => $i,
                'capital' => $capital,
                'interes' => $interes,
                'pago' => $pago,
                'saldo' => $saldo,
                'fecha' => $fecha,
            ];
            
            $fechaAnterior = $fechaActual;
        }

        return $rows;
    }

    private function buildCompoundSchedule(DateTimeImmutable $fechaBase, array $prestacion, float $tasaMensual): array
    {
        $monto = max(0.0, (float)($prestacion['monto'] ?? 0.0));
        $fechaObjetivo = DateTimeImmutable::createFromFormat('Y-m-d', (string)($prestacion['fecha'] ?? '')) ?: $fechaBase;

        if ($monto <= 0.0) {
            return [];
        }

        $dias = max(0, (int)$fechaBase->diff($fechaObjetivo)->days);
        $numQuincenas = max(1, (int)ceil($dias / 15));
        $tasaQuincenalCompuesta = pow(1 + ($tasaMensual / 100), 0.5) - 1;

        $saldo = $monto;
        $rows = [];

        for ($i = 1; $i <= $numQuincenas; $i++) {
            $interes = $saldo * $tasaQuincenalCompuesta;
            $saldoCapitalizado = $saldo + $interes;

            $capital = 0.0;
            $pago = 0.0;
            if ($i === $numQuincenas) {
                $capital = $saldoCapitalizado;
                $pago = $capital;
                $saldo = 0.0;
            } else {
                $saldo = $saldoCapitalizado;
            }

            $fechaPago = $i === $numQuincenas
                ? $fechaObjetivo->format('Y-m-d')
                : $fechaBase->modify('+' . ($i * 15) . ' days')->format('Y-m-d');

            $rows[] = [
                'periodo' => $i,
                'capital' => $capital,
                'interes' => $interes,
                'pago' => $pago,
                'saldo' => $saldo,
                'fecha' => $fechaPago,
            ];
        }

        return $rows;
    }

    private function resolveNextFortnightDate(DateTimeImmutable $date): DateTimeImmutable
    {
        $day = (int)$date->format('d');

        if ($day <= 15) {
            return DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-15')) ?: $date;
        }

        return new DateTimeImmutable($date->format('Y-m-t'));
    }
}


