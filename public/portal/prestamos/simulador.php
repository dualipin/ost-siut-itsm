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

    $plazoDias = ($mesesPagar * 30) + $diasAdicionales;
    $resumenAnual = [];
    $formasPago = [];
    $prestacionesNoPeriodicas = [];

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
            $cantidad = max(1, (int)($desc['cantidad'] ?? 1));
            
            // Calcular el plazo considerando:
            // 1. Días hasta el primer pago (desde otorgamiento hasta primera fecha de pago)
            // 2. Períodos completos restantes
            $fechaPrimerpago = $fechaBase;
            $diaPago = max(1, (int)($cat['diasPagoTentativo'] ?? 15));
            $diasAlPrimerpago = min($diaPago, $frecuenciaDias) - 1;
            
            $totalDias = $diasAlPrimerpago + (($cantidad - 1) * $frecuenciaDias);
            $plazoDias = max($plazoDias, $totalDias);

            $formasPago[] = [
                'tipo' => 'periodico',
                'nombre' => $nombre,
                'monto' => $monto,
                'frecuenciaDias' => $frecuenciaDias,
                'cantidad' => $cantidad,
                'diaTentativo' => $diaPago,
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
    }

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
    $fechaActual = new DateTime($fechaOtorgamiento);
    $fechaActual->modify("+{$diasAdicionales} days");

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
        
        // Días adicionales para la primera quincena
        if ($i === 1 && $diasAdicionales > 0) {
            $interesQuincenal += ($montoPrestamo * $tasaDiaria * $diasAdicionales);
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

    if ($salidaPdf) {
        $resumen = [
            'montoTotal' => $montoPrestamo,
            'interesTotal' => array_reduce($corrida, static fn (float $sum, array $row): float => $sum + (float) $row['interes'], 0.0),
            'pagoTotal' => array_reduce($corrida, static fn (float $sum, array $row): float => $sum + (float) $row['pago'], 0.0),
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
            'corrida' => $corrida,
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
    ]);
} else {
    $renderer->render("./simulador.latte", [
        "categoriasTipoIngreso" => $categoriasTipoIngreso,
        "prestamistaNombre" => $prestamistaNombre,
        "isNoAgremiado" => $isNoAgremiado,
    ]);
}
