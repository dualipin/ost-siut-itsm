<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\UseCase\GetLoanDetailUseCase;
use App\Modules\Loan\Application\UseCase\SubmitLoanApplicationUseCase;
use App\Modules\Loan\Application\UseCase\ValidateSignedDocumentsUseCase;
use App\Shared\Context\UserContextInterface;
use Dompdf\Dompdf;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$currentUser = $userContext->get();
$db          = $container->get(\PDO::class);

$buildDownloadUrl = static function (?string $path): ?string {
    $path = trim((string) $path);

    if ($path === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $path) === 1) {
        return $path;
    }

    $normalizedPath = str_replace('\\', '/', $path);
    $uploadsPosition = strpos($normalizedPath, 'uploads/');

    if ($uploadsPosition !== false) {
        $normalizedPath = substr($normalizedPath, $uploadsPosition);
    } else {
        $normalizedPath = 'uploads/' . ltrim($normalizedPath, '/');
    }

    $normalizedPath = ltrim($normalizedPath, '/');

    if (!str_starts_with($normalizedPath, 'uploads/')) {
        $normalizedPath = 'uploads/' . $normalizedPath;
    }

    return '/descargar.php?path=' . rawurlencode($normalizedPath);
};

$loanId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($loanId === false || $loanId <= 0) {
    header('Location: /portal/prestamos/activos.php');
    exit;
}

/** @var GetLoanDetailUseCase $getDetailUseCase */
$getDetailUseCase = $container->get(GetLoanDetailUseCase::class);
$detail = $getDetailUseCase->execute($loanId);

if ($detail === null) {
    header('Location: /portal/prestamos/activos.php');
    exit;
}

$loan = $detail['loan'];

$privilegedRoles = ['administrador', 'finanzas', 'lider'];
$isPrivileged = in_array($currentUser->role->value, $privilegedRoles, true);

if (!$isPrivileged && (int) ($loan['user_id'] ?? 0) !== (int) $currentUser->id) {
    header('Location: /portal/acceso-denegado.php');
    exit;
}

$isOwner = (int) ($loan['user_id'] ?? 0) === (int) $currentUser->id;
$canDraftActions = $isOwner && (string) ($loan['status'] ?? '') === 'borrador';
$canUploadSignedDocs = $isOwner && (string) ($loan['status'] ?? '') === 'aprobado';
$canReuploadPaymentDocs = $isOwner;
$draftActionError = null;
$signedDocumentError = null;
$paymentDocumentError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'upload_signed_doc' && $canUploadSignedDocs) {
        try {
            $legalDocId = filter_var($_POST['legal_doc_id'] ?? '', FILTER_VALIDATE_INT);
            if ($legalDocId === false || $legalDocId <= 0) {
                throw new RuntimeException('Documento legal invalido.');
            }

            $document = null;
            foreach (($detail['legal_docs'] ?? []) as $legalDoc) {
                if ((int) ($legalDoc['legal_doc_id'] ?? 0) === (int) $legalDocId) {
                    $document = $legalDoc;
                    break;
                }
            }

            if ($document === null) {
                throw new RuntimeException('No se encontro el documento legal seleccionado.');
            }

            if (empty($document['requires_user_signature'])) {
                throw new RuntimeException('El documento seleccionado no requiere firma del prestador.');
            }

            if (!isset($_FILES['signed_document'])) {
                throw new RuntimeException('Debes seleccionar un archivo PDF firmado.');
            }

            $file = $_FILES['signed_document'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No fue posible subir el archivo firmado.');
            }

            $fileSize = (int) ($file['size'] ?? 0);
            if ($fileSize <= 0 || $fileSize > (5 * 1024 * 1024)) {
                throw new RuntimeException('El archivo debe ser PDF y no exceder 5MB.');
            }

            $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                throw new RuntimeException('Solo se permiten archivos PDF firmados.');
            }

            /** @var AppConfig $appConfig */
            $appConfig = $container->get(AppConfig::class);
            $targetDirectory = rtrim($appConfig->upload->privateDir, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'loans'
                . DIRECTORY_SEPARATOR
                . 'signed';

            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException('No se pudo crear el directorio para documentos firmados.');
            }

            $fileName = sprintf(
                'signed_%d_%d_%s.pdf',
                (int) $loan['loan_id'],
                (int) $legalDocId,
                bin2hex(random_bytes(4))
            );
            $storedPath = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
                throw new RuntimeException('No se pudo almacenar el archivo firmado.');
            }

            /** @var ValidateSignedDocumentsUseCase $validateDocumentsUseCase */
            $validateDocumentsUseCase = $container->get(ValidateSignedDocumentsUseCase::class);
            $validateDocumentsUseCase->uploadSignedDocument(
                loanId: (int) $loan['loan_id'],
                legalDocId: (int) $legalDocId,
                signedFilePath: $storedPath,
            );

            header('Location: /portal/prestamos/activo-detalle.php?id=' . (int) $loan['loan_id'] . '&signed_uploaded=1');
            exit;
        } catch (\Throwable $e) {
            $signedDocumentError = 'No fue posible subir el documento firmado. ' . $e->getMessage();
        }
    }

    if ($action === 'upload_payment_doc' && $canReuploadPaymentDocs) {
        try {
            $paymentConfigId = filter_var($_POST['payment_config_id'] ?? '', FILTER_VALIDATE_INT);
            if ($paymentConfigId === false || $paymentConfigId <= 0) {
                throw new RuntimeException('Configuracion de pago invalida.');
            }

            $paymentConfig = null;
            foreach (($detail['payment_configs'] ?? []) as $config) {
                if ((int) ($config['payment_config_id'] ?? 0) === (int) $paymentConfigId) {
                    $paymentConfig = $config;
                    break;
                }
            }

            if ($paymentConfig === null) {
                throw new RuntimeException('No se encontro la configuracion de pago seleccionada.');
            }

            $documentStatus = strtolower(trim((string) ($paymentConfig['document_status'] ?? '')));
            if ($documentStatus !== 'rechazado') {
                throw new RuntimeException('Solo se puede reemplazar el archivo cuando el documento esta rechazado.');
            }

            if (!isset($_FILES['payment_document'])) {
                throw new RuntimeException('Debes seleccionar un archivo PDF para reenviar.');
            }

            $file = $_FILES['payment_document'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No fue posible subir el archivo seleccionado.');
            }

            $fileSize = (int) ($file['size'] ?? 0);
            if ($fileSize <= 0 || $fileSize > (5 * 1024 * 1024)) {
                throw new RuntimeException('El archivo debe ser PDF y no exceder 5MB.');
            }

            $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                throw new RuntimeException('Solo se permiten archivos PDF.');
            }

            $uploadDir = __DIR__ . '/../../../uploads/solicitudes/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('No se pudo preparar el directorio de carga.');
            }

            $fileName = sprintf(
                'reenvio_doc_%d_%d_%s.pdf',
                (int) $loan['loan_id'],
                (int) $paymentConfigId,
                bin2hex(random_bytes(4))
            );
            $storedPath = $uploadDir . $fileName;

            if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
                throw new RuntimeException('No se pudo almacenar el nuevo documento.');
            }

            $relativePath = '/uploads/solicitudes/' . $fileName;

            $updatePaymentDocumentStmt = $db->prepare(
                'UPDATE loan_payment_configuration
                 SET supporting_document_path = :supporting_document_path,
                     document_status = :document_status,
                     document_observations = NULL,
                     document_validation_date = NULL
                 WHERE payment_config_id = :payment_config_id
                   AND loan_id = :loan_id'
            );

            $updatePaymentDocumentStmt->execute([
                'supporting_document_path' => $relativePath,
                'document_status' => 'pendiente',
                'payment_config_id' => (int) $paymentConfigId,
                'loan_id' => (int) $loan['loan_id'],
            ]);

            if ($updatePaymentDocumentStmt->rowCount() <= 0) {
                throw new RuntimeException('No fue posible actualizar el documento de la configuracion seleccionada.');
            }

            header('Location: /portal/prestamos/activo-detalle.php?id=' . (int) $loan['loan_id'] . '&payment_doc_uploaded=1');
            exit;
        } catch (\Throwable $e) {
            $paymentDocumentError = 'No fue posible reenviar el documento de pago. ' . $e->getMessage();
        }
    }

    if ($action === 'submit_draft' && $canDraftActions) {
        try {
            /** @var SubmitLoanApplicationUseCase $submitLoanUseCase */
            $submitLoanUseCase = $container->get(SubmitLoanApplicationUseCase::class);
            $submitLoanUseCase->submit((int) $loan['loan_id']);

            header('Location: /portal/prestamos/activo-detalle.php?id=' . (int) $loan['loan_id'] . '&submitted=1');
            exit;
        } catch (\Throwable $e) {
            $draftActionError = 'No fue posible enviar el borrador. ' . $e->getMessage();
        }
    }
}

$statusLabels = [
    'borrador' => 'Borrador',
    'solicitado' => 'Solicitado',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'en_espera' => 'En espera',
    'activo' => 'Activo',
    'desembolsado' => 'Activo',
    'liquidado' => 'Liquidado',
    'reestructurado' => 'Reestructurado',
];

$statusBadges = [
    'borrador' => 'bg-light text-dark',
    'solicitado' => 'bg-warning-subtle text-warning',
    'aprobado' => 'bg-info-subtle text-info',
    'rechazado' => 'bg-danger-subtle text-danger',
    'en_espera' => 'bg-dark-subtle text-secondary',
    'activo' => 'bg-success-subtle text-success',
    'desembolsado' => 'bg-success-subtle text-success',
    'liquidado' => 'bg-secondary-subtle text-secondary',
    'reestructurado' => 'bg-primary-subtle text-primary',
];

$loan['status_label'] = $statusLabels[$loan['status']] ?? ucfirst((string) $loan['status']);
$loan['status_badge'] = $statusBadges[$loan['status']] ?? 'bg-light text-dark';
$loan['requested_amount_label'] = '$' . number_format((float) $loan['requested_amount'], 2, ',', '.');
$loan['approved_amount_label'] = $loan['approved_amount'] !== null
    ? '$' . number_format((float) $loan['approved_amount'], 2, ',', '.')
    : '—';
$loan['outstanding_balance_label'] = '$' . number_format((float) $loan['outstanding_balance'], 2, ',', '.');
$loan['estimated_total_label'] = $loan['estimated_total_to_pay'] !== null
    ? '$' . number_format((float) $loan['estimated_total_to_pay'], 2, ',', '.')
    : '—';
$loan['application_date_label'] = !empty($loan['application_date'])
    ? date('d/m/Y H:i', strtotime((string) $loan['application_date']))
    : '—';
$loan['approval_date_label'] = !empty($loan['approval_date'])
    ? date('d/m/Y H:i', strtotime((string) $loan['approval_date']))
    : '—';
$loan['disbursement_date_label'] = !empty($loan['disbursement_date'])
    ? date('d/m/Y H:i', strtotime((string) $loan['disbursement_date']))
    : '—';
$loan['first_payment_date_label'] = !empty($loan['first_payment_date'])
    ? date('d/m/Y', strtotime((string) $loan['first_payment_date']))
    : '—';
$loan['last_payment_date_label'] = !empty($loan['last_scheduled_payment_date'])
    ? date('d/m/Y', strtotime((string) $loan['last_scheduled_payment_date']))
    : '—';

$amortization = $detail['amortization'] ?? [];
$totals = [
    'principal' => 0.0,
    'interest' => 0.0,
    'payment' => 0.0,
];

foreach ($amortization as &$row) {
    $row['scheduled_date_label'] = !empty($row['scheduled_date'])
        ? date('d/m/Y', strtotime((string) $row['scheduled_date']))
        : '—';
    $row['initial_balance_label'] = '$' . number_format((float) $row['initial_balance'], 2, ',', '.');
    $row['principal_label'] = '$' . number_format((float) $row['principal'], 2, ',', '.');
    $row['ordinary_interest_label'] = '$' . number_format((float) $row['ordinary_interest'], 2, ',', '.');
    $row['total_scheduled_payment_label'] = '$' . number_format((float) $row['total_scheduled_payment'], 2, ',', '.');
    $row['final_balance_label'] = '$' . number_format((float) $row['final_balance'], 2, ',', '.');

    $totals['principal'] += (float) $row['principal'];
    $totals['interest'] += (float) $row['ordinary_interest'];
    $totals['payment'] += (float) $row['total_scheduled_payment'];
}
unset($row);

$paymentConfigs = $detail['payment_configs'] ?? [];
foreach ($paymentConfigs as &$config) {
    $config['supporting_document_url'] = $buildDownloadUrl($config['supporting_document_path'] ?? null);
}
unset($config);
$detail['payment_configs'] = $paymentConfigs;

$detail['legal_docs'] = array_map(
    static function (array $doc) use ($buildDownloadUrl): array {
        $doc['download_url'] = $buildDownloadUrl($doc['file_path'] ?? null);
        $doc['signed_download_url'] = $buildDownloadUrl($doc['user_signature_url'] ?? null);

        return $doc;
    },
    $detail['legal_docs'] ?? []
);

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

    $html = $renderer->renderToString(__DIR__ . '/pdf-detalle-amortizacion.latte', [
        'loan' => $loan,
        'detail' => $detail,
        'corridasPorTipo' => $corridasPorTipo,
        'resumen' => $resumen,
        'fecha_generacion' => (new DateTimeImmutable())->format('d/m/Y H:i'),
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

$totals['principal_label'] = '$' . number_format($totals['principal'], 2, ',', '.');
$totals['interest_label'] = '$' . number_format($totals['interest'], 2, ',', '.');
$totals['payment_label'] = '$' . number_format($totals['payment'], 2, ',', '.');

$overdueStatement = $db->prepare(
    'SELECT COUNT(*) AS overdue_installments, COALESCE(MAX(days_overdue), 0) AS max_days_overdue
     FROM loan_amortization
     WHERE loan_id = :loan_id
       AND active = 1
       AND days_overdue > 0'
);
$overdueStatement->execute(['loan_id' => (int) $loan['loan_id']]);
$overdueRow = $overdueStatement->fetch(\PDO::FETCH_ASSOC) ?: ['overdue_installments' => 0, 'max_days_overdue' => 0];

$summary = [
    'overdue_installments' => (int) ($overdueRow['overdue_installments'] ?? 0),
    'max_days_overdue' => (int) ($overdueRow['max_days_overdue'] ?? 0),
    'amortization_rows' => count($amortization),
    'legal_docs' => count($detail['legal_docs'] ?? []),
];

$renderer->render(__DIR__ . '/activo-detalle.latte', [
    'loan' => $loan,
    'detail' => $detail,
    'amortization' => $amortization,
    'totals' => $totals,
    'summary' => $summary,
    'can_draft_actions' => $canDraftActions,
    'draft_action_error' => $draftActionError,
    'draft_action_success' => isset($_GET['submitted']) && $_GET['submitted'] === '1',
    'can_upload_signed_docs' => $canUploadSignedDocs,
    'signed_upload_error' => $signedDocumentError,
    'signed_upload_success' => isset($_GET['signed_uploaded']) && $_GET['signed_uploaded'] === '1',
    'can_reupload_payment_docs' => $canReuploadPaymentDocs,
    'payment_doc_upload_error' => $paymentDocumentError,
    'payment_doc_upload_success' => isset($_GET['payment_doc_uploaded']) && $_GET['payment_doc_uploaded'] === '1',
]);
