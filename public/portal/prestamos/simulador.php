<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserContextInterface;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();
$renderer = $container->get(RendererInterface::class);
$pdo = $container->get(PDO::class);
$userContext = $container->get(UserContextInterface::class);

$user = $userContext->get();
$prestamistaNombre = $user ? $user->name : "Usuario Anónimo";
$rolUsuario = 'agremiado';
if ($user && isset($user->role)) {
    $rolRaw = is_object($user->role) && isset($user->role->value)
        ? (string)$user->role->value
        : (string)$user->role;
    $rolUsuario = strtolower($rolRaw);
}
$isNoAgremiado = $rolUsuario === 'no_agremiado';

$form = new FormRequest();
$salidaPdf = $form->input('output', 'html') === 'pdf';

// Fetch categories for the form
$stmt = $pdo->query("SELECT 
    income_type_id as id, 
    name as nombre, 
    is_periodic as esPeriodico, 
    frequency_days as frecuenciaDias, 
    tentative_payment_month as mesPagoTentativo, 
    tentative_payment_day as diasPagoTentativo, 
    active 
FROM cat_income_types 
WHERE active = 1");

$categoriasTipoIngreso = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert numeric types to booleans and integers properly for JSON
foreach ($categoriasTipoIngreso as &$cat) {
    $cat['esPeriodico'] = (bool)$cat['esPeriodico'];
    $cat['activo'] = (bool)$cat['active'];
    $cat['frecuenciaDias'] = $cat['frecuenciaDias'] ? (int)$cat['frecuenciaDias'] : null;
    $cat['mesPagoTentativo'] = $cat['mesPagoTentativo'] ? (int)$cat['mesPagoTentativo'] : null;
    $cat['diasPagoTentativo'] = $cat['diasPagoTentativo'] ? (int)$cat['diasPagoTentativo'] : null;
}
unset($cat);

if ($form->method() == "POST") {
    $firstPaymentToleranceDays = 15;

    $findCategoriaById = static function (array $categorias, int $tipoId): ?array {
        foreach ($categorias as $categoria) {
            if ((int)($categoria['id'] ?? 0) === $tipoId) {
                return $categoria;
            }
        }

        return null;
    };

    $resolvePagoDate = static function (DateTimeImmutable $baseDate, int $month, int $day): DateTimeImmutable {
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
    };

    $buildPeriodicOptions = static function (DateTimeImmutable $inicio, array $cat) use ($firstPaymentToleranceDays): array {
        if (empty($cat['esPeriodico'])) {
            return [];
        }

        $inicioElegible = $inicio->add(new DateInterval('P' . $firstPaymentToleranceDays . 'D'));
        $frecuencia = max(1, (int)($cat['frecuenciaDias'] ?? 30));
        $diaPago = max(1, (int)($cat['diasPagoTentativo'] ?? 15));
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
    };

    $buildGermanSimpleSchedule = static function (DateTimeImmutable $fechaBase, array $prestacion, float $tasaMensual): array {
        $monto = max(0.0, (float)($prestacion['monto'] ?? 0));
        $cantidad = max(1, (int)($prestacion['cantidad'] ?? 1));
        $frecuenciaDias = max(1, (int)($prestacion['frecuenciaDias'] ?? 15));
        $fechas = is_array($prestacion['fechas'] ?? null) ? $prestacion['fechas'] : [];

        if ($monto <= 0) {
            return [];
        }

        $tasaPeriodoSimple = ($tasaMensual / 100) * ($frecuenciaDias / 30);
        $capitalFijo = $monto / $cantidad;
        $saldo = $monto;
        $rows = [];

        for ($i = 1; $i <= $cantidad; $i++) {
            $fecha = $fechas[$i - 1] ?? null;
            if (!is_string($fecha) || trim($fecha) === '') {
                $fecha = $fechaBase->modify('+' . ($frecuenciaDias * $i) . ' days')->format('Y-m-d');
            }

            $interes = $saldo * $tasaPeriodoSimple;
            $capital = min($capitalFijo, $saldo);
            $pago = $capital + $interes;
            $saldo -= $capital;
            if ($saldo < 0) {
                $saldo = 0;
            }

            $rows[] = [
                'periodo' => $i,
                'capital' => $capital,
                'interes' => $interes,
                'pago' => $pago,
                'saldo' => $saldo,
                'fecha' => $fecha,
            ];
        }

        return $rows;
    };

    $buildCompoundSchedule = static function (DateTimeImmutable $fechaBase, array $prestacion, float $tasaMensual): array {
        $monto = max(0.0, (float)($prestacion['monto'] ?? 0));
        $fechaObjetivo = DateTimeImmutable::createFromFormat('Y-m-d', (string)($prestacion['fecha'] ?? '')) ?: $fechaBase;

        if ($monto <= 0) {
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
    };

    $res = $form->input("descuentos_json");
    $descuentos = json_decode($res, true) ?? [];

    // Additional parameters from the user
    $prestamistaNombre = $form->input("prestamista_nombre", $prestamistaNombre); // Keep as fallback
    $montoPrestamo = max(0.0, (float) $form->input("monto_prestamo", 0));
    $fechaOtorgamiento = $form->input("fecha_otorgamiento", date('Y-m-d'));
    $mesesPagar = max(0, (int) $form->input("meses_pagar", 0));
    $diasAdicionales = max(0, (int) $form->input("dias_adicionales", 0));
    $tasaInteresMensual = (float) $form->input("tasa_interes", 0);

    $fechaBase = DateTimeImmutable::createFromFormat('Y-m-d', $fechaOtorgamiento) ?: new DateTimeImmutable();

    $resolveNextFortnightDate = static function (DateTimeImmutable $date): DateTimeImmutable {
        $day = (int)$date->format('d');

        if ($day <= 15) {
            return DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-15')) ?: $date;
        }

        return new DateTimeImmutable($date->format('Y-m-t'));
    };

    $plazoDias = ($mesesPagar * 30) + $diasAdicionales;
    $resumenAnual = [];
    $formasPago = [];
    $prestacionesNoPeriodicas = [];
    $corridaPrestaciones = [];
    $acumuladoPrestaciones = [];
    $prestacionesParaCorrida = [];

    foreach ($descuentos as $desc) {
        $tipoId = (int)($desc['tipoId'] ?? 0);
        $monto = (float)($desc['monto'] ?? 0);
        if ($tipoId <= 0 || $monto <= 0) {
            continue;
        }

        $cat = $findCategoriaById($categoriasTipoIngreso, $tipoId);
        if ($cat === null) {
            continue;
        }

        $nombre = (string)($cat['nombre'] ?? 'Prestación');

        if (!empty($cat['esPeriodico'])) {
            $frecuenciaDias = max(1, (int)($cat['frecuenciaDias'] ?? 15));
            $cantidadSolicitada = max(1, (int)($desc['cantidad'] ?? 1));
            $opcionesFechas = $buildPeriodicOptions($fechaBase, $cat);
            if ($opcionesFechas === []) {
                continue;
            }

            $fechaSeleccionada = !empty($desc['fechaPago']) && is_string($desc['fechaPago'])
                ? (string)$desc['fechaPago']
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

        $mesTentativo = max(1, (int)($cat['mesPagoTentativo'] ?? 12));
        $diaTentativo = max(1, (int)($cat['diasPagoTentativo'] ?? 1));
        $fechaPrestacion = $resolvePagoDate($fechaBase, $mesTentativo, $diaTentativo);
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
    $fechaReferenciaPrimerPago = $fechaBase->add(new DateInterval('P' . $firstPaymentToleranceDays . 'D'));
    $primerPago = $resolveNextFortnightDate($fechaReferenciaPrimerPago);
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
        // Calcular fecha de pago (aproximadamente quincenal)
        // Adjust to 15th or end of month
        $day = (int)$fechaActual->format('d');
        if ($day <= 15) {
            $fechaActual->setDate((int)$fechaActual->format('Y'), (int)$fechaActual->format('m'), 15);
        } else {
            $fechaActual->modify('last day of this month');
        }
        
        $fechaPagoStr = $fechaActual->format('Y-m-d');
        
        // Interes quincenal base (Sistema Aleman)
        $interesQuincenal = $saldo * $tasaQuincenal;
        
        // Días transcurridos extra hasta la primera quincena después de la tolerancia.
        if ($i === 1 && $diasExtraPrimeraQuincena > 0) {
            $interesQuincenal += ($montoPrestamo * $tasaDiaria * $diasExtraPrimeraQuincena);
        }
        
        // Buscar si aplica prestación no periódica como abono extraordinario en su fecha.
        $pagoExtraordinario = 0;
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
        if ($saldo < 0) $saldo = 0;
        
        $corrida[] = [
            'quincena' => $i,
            'capital' => $capitalAbono,
            'interes' => $interesQuincenal,
            'pago' => $pagoTotal,
            'saldo' => $saldo,
            'fecha' => $fechaPagoStr
        ];
        
        if ($saldo <= 0) break;
        
        // Move to next quincena
        if ((int)$fechaActual->format('d') == 15) {
            $fechaActual->modify('last day of this month');
        } else {
            $fechaActual->modify('first day of next month');
            $fechaActual->modify('+14 days'); // goes to 15th
        }
    }

    $corridasPorTipo = [];
    $interesTotalGlobal = 0.0;
    $pagoTotalGlobal = 0.0;

    foreach ($prestacionesParaCorrida as $prestacion) {
        $esPeriodico = (string)($prestacion['tipo'] ?? '') === 'periodico';
        $corridaTipo = $esPeriodico
            ? $buildGermanSimpleSchedule($fechaBase, $prestacion, $tasaInteresMensual)
            : $buildCompoundSchedule($fechaBase, $prestacion, $tasaInteresMensual);

        if ($corridaTipo === []) {
            continue;
        }

        $interesTotal = array_reduce($corridaTipo, static fn (float $sum, array $row): float => $sum + (float) $row['interes'], 0.0);
        $pagoTotal = array_reduce($corridaTipo, static fn (float $sum, array $row): float => $sum + (float) $row['pago'], 0.0);

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

    if ($salidaPdf) {
        $resumen = [
            'montoTotal' => $montoPrestamo,
            'interesTotal' => $interesTotalGlobal,
            'pagoTotal' => $pagoTotalGlobal,
        ];

        $html = $renderer->renderToString('./pdf-simulados.latte', [
            'prestamistaNombre' => $prestamistaNombre,
            'montoPrestamo' => $montoPrestamo,
            'mesesPagar' => $mesesPagar,
            'diasAdicionales' => $diasAdicionales,
            'tasaInteresMensual' => $tasaInteresMensual,
            'fechaOtorgamiento' => $fechaOtorgamiento,
            'formasPago' => $formasPago,
            'resumenAnual' => $resumenAnual,
            'corridaPrestaciones' => $corridaPrestaciones,
            'corridasPorTipo' => $corridasPorTipo,
            'resumen' => $resumen,
            'fecha_simulacion' => (new DateTimeImmutable())->format('d/m/Y H:i'),
        ]);

        $pdf = new Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('Letter');
        $options = $pdf->getOptions();
        $options->setIsRemoteEnabled(true);
        $options->setIsHtml5ParserEnabled(true);
        $pdf->setOptions($options);
        $pdf->render();

        $pdf->stream('simulacion-prestamos.pdf', ['Attachment' => true]);
        exit;
    }
    
    $renderer->render("./simulador_reporte.latte", [
        "prestamistaNombre" => $prestamistaNombre,
        "montoPrestamo" => $montoPrestamo,
        "mesesPagar" => $mesesPagar,
        "diasAdicionales" => $diasAdicionales,
        "tasaInteresMensual" => $tasaInteresMensual,
        "fechaOtorgamiento" => $fechaOtorgamiento,
        "corrida" => $corrida,
        "resumenAnual" => $resumenAnual,
        "formasPago" => $formasPago,
        "corridaPrestaciones" => $corridaPrestaciones,
    ]);
} else {
    $renderer->render("./simulador.latte", [
        "categoriasTipoIngreso" => $categoriasTipoIngreso,
        "prestamistaNombre" => $prestamistaNombre,
        "isNoAgremiado" => $isNoAgremiado,
    ]);
}
