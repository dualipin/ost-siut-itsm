<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\UseCase\GetLoanDetailUseCase;
use App\Modules\Loan\Application\UseCase\SubmitLoanApplicationUseCase;
use App\Modules\Loan\Application\UseCase\ValidateSignedDocumentsUseCase;
use App\Shared\Context\UserContextInterface;

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
        $normalizedPath = ltrim($normalizedPath, '/');
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
$draftActionError = null;
$signedDocumentError = null;

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
$loan['requested_amount_label'] = '$' . number_format((float) $loan['requested_amount'], 2);
$loan['approved_amount_label'] = $loan['approved_amount'] !== null
    ? '$' . number_format((float) $loan['approved_amount'], 2)
    : '—';
$loan['outstanding_balance_label'] = '$' . number_format((float) $loan['outstanding_balance'], 2);
$loan['estimated_total_label'] = $loan['estimated_total_to_pay'] !== null
    ? '$' . number_format((float) $loan['estimated_total_to_pay'], 2)
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
    $row['initial_balance_label'] = '$' . number_format((float) $row['initial_balance'], 2);
    $row['principal_label'] = '$' . number_format((float) $row['principal'], 2);
    $row['ordinary_interest_label'] = '$' . number_format((float) $row['ordinary_interest'], 2);
    $row['total_scheduled_payment_label'] = '$' . number_format((float) $row['total_scheduled_payment'], 2);
    $row['final_balance_label'] = '$' . number_format((float) $row['final_balance'], 2);

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

$totals['principal_label'] = '$' . number_format($totals['principal'], 2);
$totals['interest_label'] = '$' . number_format($totals['interest'], 2);
$totals['payment_label'] = '$' . number_format($totals['payment'], 2);

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
]);
