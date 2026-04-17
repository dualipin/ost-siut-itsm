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
    $html = $renderer->renderToString(__DIR__ . "/pdf-detalle-amortizacion.latte", [
        "loan" => $loan,
        "detail" => $detail,
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

    $filename = 'amortizacion-' . (string)$loan['folio'] . '.pdf';
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
