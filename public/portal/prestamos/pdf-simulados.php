<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Shared\Context\UserContextInterface;
use Dompdf\Dompdf;
use Latte\Engine;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$latte = $container->get(Engine::class);
$pdo = $container->get(PDO::class);
$userContext = $container->get(UserContextInterface::class);

$user = $userContext->get();
$prestamistaNombre = $user ? $user->name : 'Usuario Anónimo';

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
foreach ($categoriasTipoIngreso as &$cat) {
    $cat['esPeriodico'] = (bool)$cat['esPeriodico'];
    $cat['activo'] = (bool)$cat['active'];
    $cat['frecuenciaDias'] = $cat['frecuenciaDias'] ? (int)$cat['frecuenciaDias'] : null;
    $cat['mesPagoTentativo'] = $cat['mesPagoTentativo'] ? (int)$cat['mesPagoTentativo'] : null;
    $cat['diasPagoTentativo'] = $cat['diasPagoTentativo'] ? (int)$cat['diasPagoTentativo'] : null;
}
unset($cat);

$descuentosJson = $_POST["descuentos_json"] ?? $_GET["descuentos_json"] ?? null;

if (!is_string($descuentosJson) || trim($descuentosJson) === '') {
    header('HTTP/1.1 400 Bad Request');
    echo 'No hay simulaciones para generar el PDF.';
    exit;
}

$descuentos = json_decode($descuentosJson, true);
if (!is_array($descuentos) || empty($descuentos)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'No hay simulaciones para generar el PDF.';
    exit;
}

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

$buildPeriodicOptions = static function (DateTimeImmutable $inicio, array $cat): array {
    if (empty($cat['esPeriodico'])) {
        return [];
    }

    $frecuencia = max(1, (int)($cat['frecuenciaDias'] ?? 30));
    $diaPago = max(1, (int)($cat['diasPagoTentativo'] ?? 15));
    $fechaMaxima = new DateTimeImmutable(date('Y') . '-11-15');

    if ($inicio > $fechaMaxima) {
        return [];
    }

    $opciones = [];
    $cursor = $inicio->setDate((int)$inicio->format('Y'), (int)$inicio->format('m'), 1);
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
            if ($fechaPago < $inicio || $fechaPago > $fechaMaxima) {
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

$montoPrestamo = max(0.0, (float)($_POST['monto_prestamo'] ?? $_GET['monto_prestamo'] ?? 0));
if ($montoPrestamo <= 0) {
    $montoPrestamo = array_reduce(
        $descuentos,
        static fn(float $sum, array $item): float => $sum + max(0.0, (float)($item['monto'] ?? 0)),
        0.0
    );
}

$fechaOtorgamiento = (string)($_POST['fecha_otorgamiento'] ?? $_GET['fecha_otorgamiento'] ?? date('Y-m-d'));
$mesesPagar = max(0, (int)($_POST['meses_pagar'] ?? $_GET['meses_pagar'] ?? 0));
$diasAdicionales = max(0, (int)($_POST['dias_adicionales'] ?? $_GET['dias_adicionales'] ?? 0));
$tasaInteresMensual = (float)($_POST['tasa_interes'] ?? $_GET['tasa_interes'] ?? 0);
$prestamistaNombre = (string)($_POST['prestamista_nombre'] ?? $_GET['prestamista_nombre'] ?? $prestamistaNombre);

$fechaBase = DateTimeImmutable::createFromFormat('Y-m-d', $fechaOtorgamiento) ?: new DateTimeImmutable();

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
$corridasPorTipo = [];
$interesTotalGlobal = 0.0;
$pagoTotalGlobal = 0.0;

foreach ($prestacionesParaCorrida as $prestacion) {
    $esPeriodico = (string)($prestacion['tipo'] ?? '') === 'periodico';
    $corrida = $esPeriodico
        ? $buildGermanSimpleSchedule($fechaBase, $prestacion, $tasaInteresMensual)
        : $buildCompoundSchedule($fechaBase, $prestacion, $tasaInteresMensual);

    if ($corrida === []) {
        continue;
    }

    $interesTotal = array_reduce($corrida, static fn(float $sum, array $row): float => $sum + (float)$row['interes'], 0.0);
    $pagoTotal = array_reduce($corrida, static fn(float $sum, array $row): float => $sum + (float)$row['pago'], 0.0);

    $interesTotalGlobal += $interesTotal;
    $pagoTotalGlobal += $pagoTotal;

    $corridasPorTipo[] = [
        'prestacion' => (string)($prestacion['nombre'] ?? 'Prestación'),
        'tipo' => (string)($prestacion['tipo'] ?? 'no_periodico'),
        'metodo' => $esPeriodico ? 'Interés simple - Método Alemán' : 'Interés compuesto',
        'montoBase' => (float)($prestacion['monto'] ?? 0.0),
        'corrida' => $corrida,
        'resumen' => [
            'interesTotal' => $interesTotal,
            'pagoTotal' => $pagoTotal,
            'saldoFinal' => (float)$corrida[count($corrida) - 1]['saldo'],
        ],
    ];
}

if ($corridasPorTipo === []) {
    header('HTTP/1.1 400 Bad Request');
    echo 'No hay simulaciones para generar el PDF.';
    exit;
}

$resumen = [
    'montoTotal' => $montoPrestamo,
    'interesTotal' => $interesTotalGlobal,
    'pagoTotal' => $pagoTotalGlobal,
];

// Renderizar HTML usando la plantilla .latte
$html = $latte->renderToString('./pdf-simulados.latte', [
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
    "fecha_simulacion" => (new \DateTimeImmutable())->format('d/m/Y H:i'),
]);

// Generar PDF
$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);
$pdf->render();

$pdf->stream('simulacion-prestamos.pdf', ['Attachment' => true]);
