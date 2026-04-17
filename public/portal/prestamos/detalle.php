<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\UseCase\GetLoanDetailUseCase;
use App\Modules\Loan\Application\UseCase\ReviewLoanApplicationUseCase;
use App\Modules\Loan\Application\UseCase\ValidateSignedDocumentsUseCase;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Modules\Loan\Domain\ValueObject\Money;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());
$runner->runOrRedirect(
    $middleware->role(RoleEnum::Lider, RoleEnum::Finanzas, RoleEnum::Admin),
);

$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$currentUser = $userContext->get();
$db = $container->get(\PDO::class);

$buildDownloadUrl = static function (?string $path): ?string {
    $path = trim((string) $path);

    if ($path === "") {
        return null;
    }

    if (preg_match("~^https?://~i", $path) === 1) {
        return $path;
    }

    $normalizedPath = str_replace("\\", "/", $path);
    $uploadsPosition = strpos($normalizedPath, "uploads/");

    if ($uploadsPosition !== false) {
        $normalizedPath = substr($normalizedPath, $uploadsPosition);
    } else {
        $normalizedPath = ltrim($normalizedPath, "/");
    }

    return "/descargar.php?path=" . rawurlencode($normalizedPath);
};

// --- Validate loan ID ---
$loanId = filter_var($_GET["id"] ?? "", FILTER_VALIDATE_INT);
if ($loanId === false || $loanId <= 0) {
    http_response_code(400);
    header("Location: /portal/prestamos/revision.php");
    exit();
}

$errors = [];
$success = null;

// -------------------------------------------------------------------------
// POST — Handle actions: aprobar | rechazar | en_espera
// -------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));
    /** @var ReviewLoanApplicationUseCase $reviewUseCase */
    $reviewUseCase = $container->get(ReviewLoanApplicationUseCase::class);

    try {
        switch ($action) {
            case "aprobar":
                $approvedAmount = filter_var(
                    $_POST["approved_amount"] ?? "",
                    FILTER_VALIDATE_FLOAT,
                );
                $interestRate = filter_var(
                    $_POST["applied_interest_rate"] ?? "",
                    FILTER_VALIDATE_FLOAT,
                );
                $dailyDefaultRate = filter_var(
                    $_POST["daily_default_rate"] ?? "",
                    FILTER_VALIDATE_FLOAT,
                );
                $termFortnights = filter_var(
                    $_POST["term_fortnights"] ?? "",
                    FILTER_VALIDATE_INT,
                );
                $adminObs =
                    trim((string) ($_POST["admin_observations"] ?? "")) ?: null;

                if ($approvedAmount === false || $approvedAmount <= 0) {
                    $errors[] = "El monto a aprobar debe ser mayor a cero.";
                    break;
                }
                if ($interestRate === false || $interestRate < 0) {
                    $errors[] = "La tasa de interés no es válida.";
                    break;
                }
                if ($termFortnights === false || $termFortnights <= 0) {
                    $errors[] = "El plazo en quincenas debe ser mayor a cero.";
                    break;
                }

                // Fetch CURPs from profiles (not from form — per user requirement)
                $borrowerStmt = $db->prepare(
                    "SELECT u.curp FROM loans l INNER JOIN users u ON u.user_id = l.user_id WHERE l.loan_id = :id",
                );
                $borrowerStmt->execute(["id" => $loanId]);
                $borrowerCurp = (string) ($borrowerStmt->fetchColumn() ?? "");

                $reviewerStmt = $db->prepare(
                    "SELECT curp FROM users WHERE user_id = :id",
                );
                $reviewerStmt->execute(["id" => $currentUser->id]);
                $reviewerCurp = (string) ($reviewerStmt->fetchColumn() ?? "");

                $reviewUseCase->approve(
                    loanId: $loanId,
                    reviewerId: $currentUser->id,
                    approvedAmount: new Money($approvedAmount),
                    appliedRate: InterestRate::fromPercentage($interestRate),
                    dailyDefaultRate: (float) ($dailyDefaultRate ?: 0),
                    termFortnights: $termFortnights,
                    financeSignatoryCurp: $reviewerCurp,
                    lenderSignatoryCurp: $borrowerCurp,
                    adminObservations: $adminObs,
                );
                $success = "El préstamo ha sido aprobado correctamente.";
                break;

            case "rechazar":
                $rejectionReason = trim(
                    (string) ($_POST["rejection_reason"] ?? ""),
                );
                if ($rejectionReason === "") {
                    $errors[] = "Debes proporcionar el motivo de rechazo.";
                    break;
                }
                $reviewUseCase->reject(
                    $loanId,
                    $currentUser->id,
                    $rejectionReason,
                );
                $success = "El préstamo ha sido rechazado.";
                break;

            case "en_espera":
                $reason = trim((string) ($_POST["hold_reason"] ?? "")) ?: null;
                $reviewUseCase->putOnHold($loanId, $currentUser->id, $reason);
                $success = "La solicitud ha sido puesta en espera.";
                break;

            case "validar_legal_doc":
                $legalDocId = filter_var(
                    $_POST["legal_doc_id"] ?? "",
                    FILTER_VALIDATE_INT,
                );
                $validationStatus = trim(
                    (string) ($_POST["validation_status"] ?? ""),
                );
                $validationObservations =
                    trim((string) ($_POST["validation_observations"] ?? "")) ?:
                    null;

                if ($legalDocId === false || $legalDocId <= 0) {
                    $errors[] = "ID de documento legal invalido.";
                    break;
                }

                if (
                    !in_array(
                        $validationStatus,
                        ["validado", "rechazado"],
                        true,
                    )
                ) {
                    $errors[] = "Estado de validacion no permitido.";
                    break;
                }

                /** @var ValidateSignedDocumentsUseCase $validateUseCase */
                $validateUseCase = $container->get(
                    ValidateSignedDocumentsUseCase::class,
                );
                $result = $validateUseCase->reviewSignedDocument(
                    loanId: $loanId,
                    legalDocId: (int) $legalDocId,
                    reviewerId: $currentUser->id,
                    validationStatus: $validationStatus,
                    observations: $validationObservations,
                );

                $success = $result["message"];
                break;

            default:
                $errors[] = "Acción no reconocida.";
        }
    } catch (LoanNotFoundException $e) {
        $errors[] = "El préstamo no fue encontrado.";
    } catch (InvalidLoanStatusException $e) {
        $errors[] =
            "El préstamo no puede ser procesado en su estado actual: " .
            $e->getMessage();
    } catch (\Throwable $e) {
        $errors[] = "Error inesperado: " . $e->getMessage();
    }
}

// -------------------------------------------------------------------------
// GET / POST — Load full detail aggregate
// -------------------------------------------------------------------------
try {
    /** @var GetLoanDetailUseCase $getDetailUseCase */
    $getDetailUseCase = $container->get(GetLoanDetailUseCase::class);
    $detail = $getDetailUseCase->execute($loanId);
} catch (\Throwable $e) {
    $detail = null;
}

if ($detail === null) {
    http_response_code(404);
    $renderer->render(__DIR__ . "/../acceso-denegado.latte", [
        "user" => $currentUser,
    ]);
    exit();
}

$detail["payment_configs"] = array_map(static function (array $config) use (
    $buildDownloadUrl,
): array {
    $config["supporting_document_url"] = $buildDownloadUrl(
        $config["supporting_document_path"] ?? null,
    );

    return $config;
}, $detail["payment_configs"]);

$detail["legal_docs"] = array_map(static function (array $doc) use (
    $buildDownloadUrl,
): array {
    $doc["download_url"] = $buildDownloadUrl($doc["file_path"] ?? null);
    $doc["signed_download_url"] = $buildDownloadUrl(
        $doc["user_signature_url"] ?? null,
    );

    return $doc;
}, $detail["legal_docs"]);

// Status labels & badge classes
$statusLabels = [
    "borrador" => "Borrador",
    "solicitado" => "Solicitado",
    "aprobado" => "Aprobado",
    "rechazado" => "Rechazado",
    "en_espera" => "En espera",
    "activo" => "Activo",
    "liquidado" => "Liquidado",
    "reestructurado" => "Reestructurado",
];
$statusBadges = [
    "borrador" => "bg-light text-dark",
    "solicitado" => "bg-warning-subtle text-warning",
    "aprobado" => "bg-info-subtle text-info",
    "rechazado" => "bg-danger-subtle text-danger",
    "en_espera" => "bg-dark-subtle text-secondary",
    "activo" => "bg-success-subtle text-success",
    "liquidado" => "bg-secondary-subtle text-secondary",
    "reestructurado" => "bg-primary-subtle text-primary",
];

$loan = $detail["loan"];
$currentStatus = (string) $loan["status"];

// Handle PDF download request
$form = new FormRequest();
if ($form->input('output', 'html') === 'pdf' && !empty($detail['amortization'])) {
    $loanFolio = trim((string) ($loan['folio'] ?? ''));
    if ($loanFolio === '') {
        $loanFolio = 'SIUT-FOLIO-' . (string) ($loan['loan_id'] ?? $loanId);
    }

    $paymentConfigs = is_array($detail['payment_configs'] ?? null)
        ? $detail['payment_configs']
        : [];
    $amortizationRows = is_array($detail['amortization'] ?? null)
        ? $detail['amortization']
        : [];

    $buildMethodLabel = static function (?string $method): string {
        $normalized = strtolower(trim((string) $method));

        return match ($normalized) {
            'compuesto' => 'Interés compuesto',
            'simple_aleman', '' => 'Interés simple - Método Alemán',
            default => ucfirst(str_replace('_', ' ', $normalized)),
        };
    };

    $approvalDateRaw = (string) ($loan['approval_date'] ?? $loan['application_date'] ?? '');
    $fechaBase = DateTimeImmutable::createFromFormat('Y-m-d', substr($approvalDateRaw, 0, 10))
        ?: new DateTimeImmutable();
    $tasaInteresMensual = (float) ($loan['applied_interest_rate'] ?? 0.0);

    $resolvePagoDate = static function (DateTimeImmutable $baseDate, int $month, int $day): DateTimeImmutable {
        $year = (int) $baseDate->format('Y');
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

    $buildPeriodicOptions = static function (DateTimeImmutable $inicio, array $config, int $maxCount): array {
        if ($maxCount <= 0) {
            return [];
        }

        $frecuencia = max(1, (int) ($config['income_frequency_days'] ?? 30));
        $diaPago = max(1, (int) ($config['income_payment_day'] ?? 15));
        $fechaMaxima = $inicio->modify('+24 months');

        $opciones = [];
        $cursor = $inicio->setDate((int) $inicio->format('Y'), (int) $inicio->format('m'), 1);
        $guard = 0;

        while ($cursor <= $fechaMaxima && $guard < 36 && count($opciones) < $maxCount) {
            $year = (int) $cursor->format('Y');
            $month = (int) $cursor->format('m');
            $totalDiasMes = (int) $cursor->format('t');

            $fraccionMensual = $frecuencia / $totalDiasMes;
            $vecesEnMes = $fraccionMensual > 0 ? max(1, (int) round(1 / $fraccionMensual)) : 1;
            $diaBase = min(max(1, $diaPago), $totalDiasMes);
            $saltoDias = max(1, (int) round($totalDiasMes / $vecesEnMes));

            for ($i = 0; $i < $vecesEnMes && count($opciones) < $maxCount; $i++) {
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

    $buildGermanSimpleSchedule = static function (DateTimeImmutable $fechaBaseCalc, array $prestacion, float $tasaMensual): array {
        $monto = max(0.0, (float) ($prestacion['monto'] ?? 0));
        $cantidad = max(1, (int) ($prestacion['cantidad'] ?? 1));
        $frecuenciaDias = max(1, (int) ($prestacion['frecuenciaDias'] ?? 15));
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
                $fecha = $fechaBaseCalc->modify('+' . ($frecuenciaDias * $i) . ' days')->format('Y-m-d');
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

    $buildCompoundSchedule = static function (DateTimeImmutable $fechaBaseCalc, array $prestacion, float $tasaMensual): array {
        $monto = max(0.0, (float) ($prestacion['monto'] ?? 0));
        $fechaObjetivo = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($prestacion['fecha'] ?? '')) ?: $fechaBaseCalc;

        if ($monto <= 0) {
            return [];
        }

        $dias = max(0, (int) $fechaBaseCalc->diff($fechaObjetivo)->days);
        $numQuincenas = max(1, (int) ceil($dias / 15));
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
                : $fechaBaseCalc->modify('+' . ($i * 15) . ' days')->format('Y-m-d');

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

    $buildCorridaRows = static function (array $rows): array {
        $corrida = [];
        $periodo = 1;

        foreach ($rows as $row) {
            $fechaRaw = (string) ($row['scheduled_date'] ?? '');
            $fecha = DateTimeImmutable::createFromFormat('Y-m-d', substr($fechaRaw, 0, 10));

            $corrida[] = [
                'periodo' => $periodo,
                'capital' => (float) ($row['principal'] ?? 0.0),
                'interes' => (float) ($row['ordinary_interest'] ?? 0.0),
                'pago' => (float) ($row['total_scheduled_payment'] ?? 0.0),
                'saldo' => (float) ($row['final_balance'] ?? 0.0),
                'fecha' => $fecha ? $fecha->format('Y-m-d') : $fechaRaw,
            ];

            $periodo++;
        }

        return $corrida;
    };

    $corridasPorTipo = [];
    foreach ($paymentConfigs as $config) {
        $montoBase = max(0.0, (float) ($config['total_amount_to_deduct'] ?? 0.0));
        if ($montoBase <= 0) {
            continue;
        }

        $isPeriodic = !empty($config['income_is_periodic']);
        $method = strtolower(trim((string) ($config['interest_method'] ?? ($isPeriodic ? 'simple_aleman' : 'compuesto'))));
        $cantidad = max(1, (int) ($config['number_of_installments'] ?? 1));
        $frecuenciaDias = max(1, (int) ($config['income_frequency_days'] ?? 15));

        $corridaTipo = [];
        if (!$isPeriodic && $method === 'compuesto') {
            $mesPago = max(1, (int) ($config['income_payment_month'] ?? 12));
            $diaPago = max(1, (int) ($config['income_payment_day'] ?? 1));
            $fechaPrestacion = $resolvePagoDate($fechaBase, $mesPago, $diaPago);

            $corridaTipo = $buildCompoundSchedule($fechaBase, [
                'monto' => $montoBase,
                'fecha' => $fechaPrestacion->format('Y-m-d'),
            ], $tasaInteresMensual);
        } else {
            $fechas = [];
            if ($isPeriodic) {
                $fechas = array_slice($buildPeriodicOptions($fechaBase, $config, $cantidad), 0, $cantidad);
            }

            if ($fechas === []) {
                for ($i = 1; $i <= $cantidad; $i++) {
                    $fechas[] = $fechaBase->modify('+' . ($frecuenciaDias * $i) . ' days')->format('Y-m-d');
                }

                if (!$isPeriodic && $cantidad === 1) {
                    $mesPago = max(1, (int) ($config['income_payment_month'] ?? 12));
                    $diaPago = max(1, (int) ($config['income_payment_day'] ?? 1));
                    $fechas[0] = $resolvePagoDate($fechaBase, $mesPago, $diaPago)->format('Y-m-d');
                }
            }

            $corridaTipo = $buildGermanSimpleSchedule($fechaBase, [
                'monto' => $montoBase,
                'cantidad' => $cantidad,
                'frecuenciaDias' => $frecuenciaDias,
                'fechas' => $fechas,
            ], $tasaInteresMensual);
        }

        $interesTotalTipo = array_reduce(
            $corridaTipo,
            static fn (float $sum, array $item): float => $sum + (float) ($item['interes'] ?? 0.0),
            0.0,
        );
        $pagoTotalTipo = array_reduce(
            $corridaTipo,
            static fn (float $sum, array $item): float => $sum + (float) ($item['pago'] ?? 0.0),
            0.0,
        );
        $saldoFinalTipo = $corridaTipo !== []
            ? (float) ($corridaTipo[count($corridaTipo) - 1]['saldo'] ?? 0.0)
            : 0.0;

        $nombrePrestacion = trim((string) ($config['income_type_name'] ?? ''));
        if ($nombrePrestacion === '') {
            $typeId = (int) ($config['income_type_id'] ?? 0);
            $nombrePrestacion = $typeId > 0 ? ('Tipo ' . $typeId) : ('Prestamo ' . $loanFolio);
        }

        $corridasPorTipo[] = [
            'prestacion' => $nombrePrestacion,
            'tipo' => $isPeriodic ? 'periodico' : 'no_periodico',
            'metodo' => $buildMethodLabel($method),
            'montoBase' => $montoBase,
            'corrida' => $corridaTipo,
            'resumen' => [
                'interesTotal' => $interesTotalTipo,
                'pagoTotal' => $pagoTotalTipo,
                'saldoFinal' => $saldoFinalTipo,
            ],
        ];
    }

    if ($corridasPorTipo === [] && $amortizationRows !== []) {
        $groupedByType = [];
        foreach ($amortizationRows as $row) {
            $typeId = (int) ($row['income_type_id'] ?? 0);
            if (!array_key_exists($typeId, $groupedByType)) {
                $groupedByType[$typeId] = [];
            }
            $groupedByType[$typeId][] = $row;
        }

        foreach ($groupedByType as $incomeTypeId => $rowsByType) {
            $corridaTipo = $buildCorridaRows($rowsByType);
            $interesTotalTipo = array_reduce(
                $corridaTipo,
                static fn (float $sum, array $item): float => $sum + (float) ($item['interes'] ?? 0.0),
                0.0,
            );
            $pagoTotalTipo = array_reduce(
                $corridaTipo,
                static fn (float $sum, array $item): float => $sum + (float) ($item['pago'] ?? 0.0),
                0.0,
            );
            $saldoFinalTipo = $corridaTipo !== []
                ? (float) ($corridaTipo[count($corridaTipo) - 1]['saldo'] ?? 0.0)
                : 0.0;

            $corridasPorTipo[] = [
                'prestacion' => $incomeTypeId > 0 ? ('Tipo ' . $incomeTypeId) : ('Prestamo ' . $loanFolio),
                'tipo' => count($corridaTipo) > 1 ? 'periodico' : 'no_periodico',
                'metodo' => $buildMethodLabel((string) ($loan['interest_method'] ?? 'simple_aleman')),
                'montoBase' => array_reduce(
                    $corridaTipo,
                    static fn (float $sum, array $item): float => $sum + (float) ($item['capital'] ?? 0.0),
                    0.0,
                ),
                'corrida' => $corridaTipo,
                'resumen' => [
                    'interesTotal' => $interesTotalTipo,
                    'pagoTotal' => $pagoTotalTipo,
                    'saldoFinal' => $saldoFinalTipo,
                ],
            ];
        }
    }

    $interesTotalGlobal = array_reduce(
        $corridasPorTipo,
        static fn (float $sum, array $grupo): float => $sum + (float) (($grupo['resumen']['interesTotal'] ?? 0.0)),
        0.0,
    );
    $pagoTotalGlobal = array_reduce(
        $corridasPorTipo,
        static fn (float $sum, array $grupo): float => $sum + (float) (($grupo['resumen']['pagoTotal'] ?? 0.0)),
        0.0,
    );

    $resumen = [
        'montoTotal' => (float) ($loan['approved_amount'] ?? $loan['requested_amount'] ?? 0.0),
        'interesTotal' => $interesTotalGlobal,
        'pagoTotal' => $pagoTotalGlobal,
    ];

    $html = $renderer->renderToString(__DIR__ . "/pdf-detalle-amortizacion.latte", [
        "loan" => $loan,
        "detail" => $detail,
        "corridasPorTipo" => $corridasPorTipo,
        "resumen" => $resumen,
        "fecha_generacion" => (new DateTimeImmutable())->format('d/m/Y H:i'),
    ]);

    $pdf = new Dompdf();
    $pdf->loadHtml($html);
    $pdf->setPaper('Letter');
    $options = $pdf->getOptions();
    $options->setIsRemoteEnabled(true);
    $options->setIsHtml5ParserEnabled(true);
    $pdf->setOptions($options);
    $pdf->render();

    $filename = 'amortizacion-' . $loanFolio . '.pdf';
    $pdf->stream($filename, ['Attachment' => true]);
    exit;
}

$renderer->render(__DIR__ . "/detalle.latte", [
    "user" => $currentUser,
    "detail" => $detail,
    "loan" => $loan,
    "statusLabel" => $statusLabels[$currentStatus] ?? ucfirst($currentStatus),
    "statusBadge" => $statusBadges[$currentStatus] ?? "bg-light text-dark",
    "statusLabels" => $statusLabels,
    "statusBadges" => $statusBadges,
    "errors" => $errors,
    "success" => $success,
    "canReview" => $currentStatus === "solicitado",
    "canHold" => $currentStatus === "solicitado",
    "canValidateDocs" => $currentStatus === "aprobado",
]);
