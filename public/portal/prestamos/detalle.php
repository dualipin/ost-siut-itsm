<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\FolioGenerator;
use App\Modules\Loan\Application\UseCase\GetLoanDetailUseCase;
use App\Modules\Loan\Application\UseCase\ReviewLoanApplicationUseCase;
use App\Modules\Loan\Application\UseCase\ValidateSignedDocumentsUseCase;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
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

if (($_GET['restructured'] ?? '') === '1') {
    $newLoanId = filter_var($_GET['new_loan_id'] ?? '', FILTER_VALIDATE_INT);
    $newLoanFolio = trim((string) ($_GET['new_folio'] ?? ''));

    if ($newLoanFolio === '' && $newLoanId !== false && $newLoanId > 0) {
        $newLoanFolio = 'SIUT-FOLIO-' . $newLoanId;
    }

    $success = $newLoanFolio !== ''
        ? 'La deuda fue reestructurada con recalculo financiero. Nuevo folio: ' . $newLoanFolio . '.'
        : 'La deuda fue reestructurada con recalculo financiero.';
}

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
                $interestRate = filter_var(
                    $_POST["applied_interest_rate"] ?? "",
                    FILTER_VALIDATE_FLOAT,
                );
                $dailyDefaultRate = filter_var(
                    $_POST["daily_default_rate"] ?? "",
                    FILTER_VALIDATE_FLOAT,
                );
                $adminObs =
                    trim((string) ($_POST["admin_observations"] ?? "")) ?: null;
                $approvedAmountsByType = is_array(
                    $_POST["approved_amount_by_type"] ?? null,
                )
                    ? $_POST["approved_amount_by_type"]
                    : [];
                $approvedPercentagesByType = is_array(
                    $_POST["approved_percentage_by_type"] ?? null,
                )
                    ? $_POST["approved_percentage_by_type"]
                    : [];
                $termFortnightsByType = is_array(
                    $_POST["term_fortnights_by_type"] ?? null,
                )
                    ? $_POST["term_fortnights_by_type"]
                    : [];

                if ($interestRate === false || $interestRate < 0) {
                    $errors[] = "La tasa de interés no es válida.";
                    break;
                }

                /** @var PaymentConfigRepositoryInterface $paymentConfigRepository */
                $paymentConfigRepository = $container->get(
                    PaymentConfigRepositoryInterface::class,
                );
                $paymentConfigs = $paymentConfigRepository->findByLoanIdWithIncomeType(
                    $loanId,
                );

                if ($paymentConfigs === []) {
                    $errors[] =
                        "No se puede aprobar: no hay tipos de descuento registrados para validar recibos.";
                    break;
                }

                $missingReceiptsByType = [];
                $pendingValidationByType = [];
                $rejectedReceiptsByType = [];

                foreach ($paymentConfigs as $config) {
                    $incomeTypeId = (int) ($config["income_type_id"] ?? 0);
                    $incomeTypeName = trim(
                        (string) ($config["income_type_name"] ?? ""),
                    );

                    if ($incomeTypeName === "") {
                        $incomeTypeName =
                            $incomeTypeId > 0
                                ? "Tipo " . $incomeTypeId
                                : "Tipo de descuento";
                    }

                    $documentPath = trim(
                        (string) ($config["supporting_document_path"] ?? ""),
                    );
                    $documentStatus = strtolower(
                        trim((string) ($config["document_status"] ?? "pendiente")),
                    );

                    if ($documentPath === "") {
                        $missingReceiptsByType[] = $incomeTypeName;
                        continue;
                    }

                    if ($documentStatus === "rechazado") {
                        $rejectedReceiptsByType[] = $incomeTypeName;
                        continue;
                    }

                    if ($documentStatus !== "validado") {
                        $pendingValidationByType[] = $incomeTypeName;
                    }
                }

                $missingReceiptsByType = array_values(
                    array_unique($missingReceiptsByType),
                );
                $pendingValidationByType = array_values(
                    array_unique($pendingValidationByType),
                );
                $rejectedReceiptsByType = array_values(
                    array_unique($rejectedReceiptsByType),
                );

                if ($missingReceiptsByType !== []) {
                    $errors[] =
                        "No se puede aprobar: faltan recibos en " .
                        implode(", ", $missingReceiptsByType) .
                        ".";
                }

                if ($rejectedReceiptsByType !== []) {
                    $errors[] =
                        "No se puede aprobar: hay recibos rechazados en " .
                        implode(", ", $rejectedReceiptsByType) .
                        ".";
                }

                if ($pendingValidationByType !== []) {
                    $errors[] =
                        "No se puede aprobar: valida los recibos de " .
                        implode(", ", $pendingValidationByType) .
                        ".";
                }

                $normalizedConfigsForApproval = [];
                $approvedAmount = 0.0;
                $termFortnights = 1;

                foreach ($paymentConfigs as $config) {
                    $paymentConfigId = (int) ($config["payment_config_id"] ?? 0);
                    $incomeTypeId = (int) ($config["income_type_id"] ?? 0);
                    $incomeTypeName = trim(
                        (string) ($config["income_type_name"] ?? ""),
                    );
                    $incomeLabel = $incomeTypeName !== ""
                        ? $incomeTypeName
                        : ($incomeTypeId > 0
                            ? "Tipo " . $incomeTypeId
                            : "Tipo de descuento");

                    $amountRaw = trim(
                        (string) ($approvedAmountsByType[$paymentConfigId] ?? ""),
                    );
                    $approvedAmountByType = filter_var(
                        $amountRaw,
                        FILTER_VALIDATE_FLOAT,
                    );
                    if ($approvedAmountByType === false || $approvedAmountByType <= 0) {
                        $errors[] =
                            "El monto autorizado para " .
                            $incomeLabel .
                            " debe ser mayor a cero.";
                        continue;
                    }

                    $percentageRaw = trim(
                        (string) ($approvedPercentagesByType[$paymentConfigId] ?? ""),
                    );
                    if ($percentageRaw !== "") {
                        $approvedPercentageByType = filter_var(
                            $percentageRaw,
                            FILTER_VALIDATE_FLOAT,
                        );
                        if (
                            $approvedPercentageByType === false ||
                            $approvedPercentageByType < 0 ||
                            $approvedPercentageByType > 100
                        ) {
                            $errors[] =
                                "El porcentaje autorizado para " .
                                $incomeLabel .
                                " debe estar entre 0 y 100.";
                        }
                    }

                    $isPeriodicIncome = (bool) ($config["income_is_periodic"] ?? false);
                    $installments = $isPeriodicIncome
                        ? filter_var(
                            $termFortnightsByType[$paymentConfigId] ?? "",
                            FILTER_VALIDATE_INT,
                        )
                        : 1;

                    if ($isPeriodicIncome) {
                        if ($installments === false || $installments <= 0) {
                            $errors[] =
                                "Las parcialidades para " .
                                $incomeLabel .
                                " deben ser mayores a cero.";
                            continue;
                        }

                        $termFortnights = max($termFortnights, (int) $installments);
                    }

                    $approvedAmount += (float) $approvedAmountByType;

                    $normalizedConfigsForApproval[] = [
                        "payment_config_id" => $paymentConfigId,
                        "approved_amount" => round((float) $approvedAmountByType, 2),
                        "number_of_installments" => $isPeriodicIncome
                            ? (int) $installments
                            : 1,
                    ];
                }

                $approvedAmount = round($approvedAmount, 2);
                if ($approvedAmount <= 0) {
                    $errors[] = "El monto total a aprobar debe ser mayor a cero.";
                }

                if ($errors !== []) {
                    break;
                }

                $updatePaymentConfigStmt = $db->prepare(
                    "UPDATE loan_payment_configuration
                     SET total_amount_to_deduct = :total_amount_to_deduct,
                         number_of_installments = :number_of_installments,
                         amount_per_installment = :amount_per_installment
                     WHERE payment_config_id = :payment_config_id
                       AND loan_id = :loan_id",
                );

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

                $db->beginTransaction();

                try {
                    foreach ($normalizedConfigsForApproval as $normalizedConfig) {
                        $configAmount = (float) $normalizedConfig["approved_amount"];
                        $configInstallments = max(
                            1,
                            (int) $normalizedConfig["number_of_installments"],
                        );

                        $updatePaymentConfigStmt->execute([
                            "total_amount_to_deduct" => $configAmount,
                            "number_of_installments" => $configInstallments,
                            "amount_per_installment" =>
                                $configAmount / $configInstallments,
                            "payment_config_id" => (int) $normalizedConfig["payment_config_id"],
                            "loan_id" => $loanId,
                        ]);
                    }

                    $reviewUseCase->approve(
                        loanId: $loanId,
                        reviewerId: $currentUser->id,
                        approvedAmount: new Money($approvedAmount),
                        appliedRate: InterestRate::fromPercentage($interestRate),
                        dailyDefaultRate: (float) ($dailyDefaultRate ?: 0),
                        termFortnights: max(1, $termFortnights),
                        financeSignatoryCurp: $reviewerCurp,
                        lenderSignatoryCurp: $borrowerCurp,
                        adminObservations: $adminObs,
                    );

                    $db->commit();
                } catch (\Throwable $approvalError) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    throw $approvalError;
                }

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

            case "reestructurar_anticipado":
                $newInterestRate = filter_var(
                    $_POST['new_interest_rate'] ?? '',
                    FILTER_VALIDATE_FLOAT,
                );
                $newTermFortnights = filter_var(
                    $_POST['new_term_fortnights'] ?? '',
                    FILTER_VALIDATE_INT,
                );
                $manualRestructuredAmountRaw = trim(
                    (string) ($_POST['restructured_principal_amount'] ?? ''),
                );
                $restructureReasonType = strtolower(
                    trim((string) ($_POST['restructure_reason_type'] ?? '')),
                );
                $restructureReasonDetail = trim(
                    (string) ($_POST['restructure_reason_detail'] ?? ''),
                );
                $restructureObservationsRaw = trim(
                    (string) ($_POST['restructure_observations'] ?? ''),
                );

                $manualRestructuredAmount = null;
                if ($manualRestructuredAmountRaw !== '') {
                    $manualRestructuredAmountParsed = filter_var(
                        $manualRestructuredAmountRaw,
                        FILTER_VALIDATE_FLOAT,
                    );

                    if (
                        $manualRestructuredAmountParsed === false ||
                        (float) $manualRestructuredAmountParsed <= 0
                    ) {
                        $errors[] = 'El monto manual para reestructuración debe ser mayor a cero.';
                        break;
                    }

                    $manualRestructuredAmount = round(
                        (float) $manualRestructuredAmountParsed,
                        2,
                    );
                }

                $reasonLabels = [
                    'mora' => 'Mora',
                    'liquidacion_anticipada' => 'Liquidación anticipada',
                    'otro' => 'Otro',
                ];

                if ($newInterestRate === false || $newInterestRate < 0 || $newInterestRate > 100) {
                    $errors[] = 'La nueva tasa de interés debe estar entre 0 y 100.';
                    break;
                }

                if ($newTermFortnights === false || $newTermFortnights <= 0) {
                    $errors[] = 'El nuevo plazo en quincenas debe ser mayor a cero.';
                    break;
                }

                if (!array_key_exists($restructureReasonType, $reasonLabels)) {
                    $errors[] = 'Selecciona un motivo de reestructuración válido.';
                    break;
                }

                if ($restructureReasonType === 'otro' && $restructureReasonDetail === '') {
                    $errors[] = 'Debes detallar el motivo cuando seleccionas "Otro".';
                    break;
                }

                $restructureReason = $restructureReasonType;
                $restructureReasonLabel = $reasonLabels[$restructureReasonType];
                $auditReasonText = $restructureReasonLabel;

                if ($restructureReasonType === 'otro' && $restructureReasonDetail !== '') {
                    $auditReasonText .= ': ' . $restructureReasonDetail;
                }

                $observationLines = [];
                if ($restructureReasonType === 'otro' && $restructureReasonDetail !== '') {
                    $observationLines[] = 'Detalle de motivo: ' . $restructureReasonDetail;
                }
                if ($restructureObservationsRaw !== '') {
                    $observationLines[] = $restructureObservationsRaw;
                }
                $restructureObservations = $observationLines !== []
                    ? implode(PHP_EOL, $observationLines)
                    : null;

                $loanStatement = $db->prepare(
                    'SELECT *
                     FROM loans
                     WHERE loan_id = :loan_id
                       AND deletion_date IS NULL
                     LIMIT 1'
                );
                $loanStatement->execute(['loan_id' => $loanId]);
                $loanRow = $loanStatement->fetch(\PDO::FETCH_ASSOC);

                if (!$loanRow) {
                    $errors[] = 'El préstamo no existe o no está disponible.';
                    break;
                }

                $loanStatus = strtolower(trim((string) ($loanRow['status'] ?? '')));
                if (!in_array($loanStatus, ['activo', 'desembolsado'], true)) {
                    $errors[] = 'Solo se puede reestructurar un préstamo activo.';
                    break;
                }

                $unpaidRowsStatement = $db->prepare(
                    "SELECT
                        amortization_id,
                        payment_number,
                        income_type_id,
                        principal,
                        ordinary_interest,
                        generated_default_interest
                     FROM loan_amortization
                     WHERE loan_id = :loan_id
                       AND active = 1
                       AND payment_status IN ('pendiente', 'vencido')
                     ORDER BY payment_number ASC"
                );
                $unpaidRowsStatement->execute(['loan_id' => $loanId]);
                $unpaidRows = $unpaidRowsStatement->fetchAll(\PDO::FETCH_ASSOC);

                if ($unpaidRows === []) {
                    $errors[] = 'No hay pagos pendientes para recalcular en una reestructuración.';
                    break;
                }

                $pendingPrincipal = array_reduce(
                    $unpaidRows,
                    static fn(float $sum, array $row): float => $sum + (float) ($row['principal'] ?? 0),
                    0.0,
                );
                $pendingInterest = array_reduce(
                    $unpaidRows,
                    static fn(float $sum, array $row): float => $sum + (float) ($row['ordinary_interest'] ?? 0),
                    0.0,
                );
                $pendingDefaultInterest = array_reduce(
                    $unpaidRows,
                    static fn(float $sum, array $row): float => $sum + (float) ($row['generated_default_interest'] ?? 0),
                    0.0,
                );

                $originalOutstandingBalance = (float) ($loanRow['outstanding_balance'] ?? 0.0);
                $baseOutstandingBalance = $pendingPrincipal > 0
                    ? $pendingPrincipal
                    : $originalOutstandingBalance;
                $calculatedRestructuredPrincipal = round(
                    $baseOutstandingBalance + $pendingInterest + $pendingDefaultInterest,
                    2,
                );
                $restructuredPrincipal = $manualRestructuredAmount ?? $calculatedRestructuredPrincipal;

                if ($restructuredPrincipal <= 0) {
                    $errors[] = $manualRestructuredAmount === null
                        ? 'No fue posible determinar un saldo base válido para reestructurar.'
                        : 'El monto manual para reestructuración debe ser mayor a cero.';
                    break;
                }

                $sourceConfigStatement = $db->prepare(
                    'SELECT
                        lpc.*,
                        cit.income_type_id AS resolved_income_type_id
                     FROM loan_payment_configuration lpc
                     INNER JOIN cat_income_types cit ON cit.income_type_id = lpc.income_type_id
                     WHERE lpc.loan_id = :loan_id
                     ORDER BY lpc.payment_config_id ASC
                     LIMIT 1'
                );
                $sourceConfigStatement->execute(['loan_id' => $loanId]);
                $sourceConfig = $sourceConfigStatement->fetch(\PDO::FETCH_ASSOC) ?: [];

                $defaultIncomeTypeId = (int) ($sourceConfig['resolved_income_type_id'] ?? ($unpaidRows[0]['income_type_id'] ?? 1));

                /** @var AmortizationCalculator $amortizationCalculator */
                $amortizationCalculator = $container->get(AmortizationCalculator::class);
                /** @var FolioGenerator $folioGenerator */
                $folioGenerator = $container->get(FolioGenerator::class);

                $restructureDate = new DateTimeImmutable();
                $newScheduleRows = $amortizationCalculator->calculateGermanSimple(
                    Money::fromFloat($restructuredPrincipal),
                    InterestRate::fromPercentage((float) $newInterestRate),
                    (int) $newTermFortnights,
                    $restructureDate,
                    $defaultIncomeTypeId,
                );

                if ($newScheduleRows === []) {
                    $errors[] = 'No fue posible generar la nueva corrida financiera.';
                    break;
                }

                $firstPaymentDate = $newScheduleRows[0]->scheduledDate()->format('Y-m-d');
                $lastPaymentDate = $newScheduleRows[count($newScheduleRows) - 1]
                    ->scheduledDate()
                    ->format('Y-m-d');

                $recalculatedInterest = array_reduce(
                    $newScheduleRows,
                    static fn(float $sum, $row): float => $sum + $row->ordinaryInterest()->amount(),
                    0.0,
                );
                $newTotalAmount = round($restructuredPrincipal + $recalculatedInterest, 2);
                $newFolio = $folioGenerator->generate($restructureDate)->toString();

                $loanInsertStatement = $db->prepare(
                    'INSERT INTO loans (
                        user_id,
                        folio,
                        requested_amount,
                        approved_amount,
                        applied_interest_rate,
                        daily_default_rate,
                        estimated_total_to_pay,
                        outstanding_balance,
                        term_fortnights,
                        first_payment_date,
                        last_scheduled_payment_date,
                        application_date,
                        approval_date,
                        disbursement_date,
                        status,
                        original_loan_id,
                        admin_observations,
                        internal_observations,
                        finance_signatory,
                        lender_signatory,
                        requires_restructuring,
                        created_by
                    ) VALUES (
                        :user_id,
                        :folio,
                        :requested_amount,
                        :approved_amount,
                        :applied_interest_rate,
                        :daily_default_rate,
                        :estimated_total_to_pay,
                        :outstanding_balance,
                        :term_fortnights,
                        :first_payment_date,
                        :last_scheduled_payment_date,
                        :application_date,
                        :approval_date,
                        :disbursement_date,
                        :status,
                        :original_loan_id,
                        :admin_observations,
                        :internal_observations,
                        :finance_signatory,
                        :lender_signatory,
                        :requires_restructuring,
                        :created_by
                    )'
                );

                $paymentConfigInsertStatement = $db->prepare(
                    'INSERT INTO loan_payment_configuration (
                        loan_id,
                        income_type_id,
                        total_amount_to_deduct,
                        number_of_installments,
                        amount_per_installment,
                        interest_method,
                        supporting_document_path,
                        document_status,
                        document_observations,
                        document_validation_date
                    ) VALUES (
                        :loan_id,
                        :income_type_id,
                        :total_amount_to_deduct,
                        :number_of_installments,
                        :amount_per_installment,
                        :interest_method,
                        :supporting_document_path,
                        :document_status,
                        :document_observations,
                        :document_validation_date
                    )'
                );

                $amortizationInsertStatement = $db->prepare(
                    'INSERT INTO loan_amortization (
                        loan_id,
                        payment_number,
                        income_type_id,
                        scheduled_date,
                        initial_balance,
                        principal,
                        ordinary_interest,
                        total_scheduled_payment,
                        final_balance,
                        payment_status,
                        table_version,
                        active
                    ) VALUES (
                        :loan_id,
                        :payment_number,
                        :income_type_id,
                        :scheduled_date,
                        :initial_balance,
                        :principal,
                        :ordinary_interest,
                        :total_scheduled_payment,
                        :final_balance,
                        :payment_status,
                        :table_version,
                        :active
                    )'
                );

                $deactivatePendingRowsStatement = $db->prepare(
                    "UPDATE loan_amortization
                     SET active = 0
                     WHERE loan_id = :loan_id
                       AND active = 1
                       AND payment_status IN ('pendiente', 'vencido')"
                );

                $oldLoanUpdateStatement = $db->prepare(
                    "UPDATE loans
                     SET status = :status,
                         outstanding_balance = :outstanding_balance,
                         total_liquidation_date = :total_liquidation_date,
                         requires_restructuring = 0
                     WHERE loan_id = :loan_id"
                );

                $restructuringInsertStatement = $db->prepare(
                    'INSERT INTO loan_restructurings (
                        original_loan_id,
                        new_loan_id,
                        reason,
                        original_outstanding_balance,
                        pending_interest,
                        pending_default_interest,
                        new_total_amount,
                        new_interest_rate,
                        new_term_fortnights,
                        restructuring_date,
                        authorized_by,
                        observations
                    ) VALUES (
                        :original_loan_id,
                        :new_loan_id,
                        :reason,
                        :original_outstanding_balance,
                        :pending_interest,
                        :pending_default_interest,
                        :new_total_amount,
                        :new_interest_rate,
                        :new_term_fortnights,
                        :restructuring_date,
                        :authorized_by,
                        :observations
                    )'
                );

                $eventInsertStatement = $db->prepare(
                    'INSERT INTO loan_events (loan_id, event_type, description, event_date)
                     VALUES (:loan_id, :event_type, :description, :event_date)'
                );

                $now = $restructureDate->format('Y-m-d H:i:s');

                $db->beginTransaction();

                try {
                    $loanInsertStatement->execute([
                        'user_id' => (int) $loanRow['user_id'],
                        'folio' => $newFolio,
                        'requested_amount' => $restructuredPrincipal,
                        'approved_amount' => $restructuredPrincipal,
                        'applied_interest_rate' => (float) $newInterestRate,
                        'daily_default_rate' => (float) ($loanRow['daily_default_rate'] ?? 0),
                        'estimated_total_to_pay' => $newTotalAmount,
                        'outstanding_balance' => $restructuredPrincipal,
                        'term_fortnights' => (int) $newTermFortnights,
                        'first_payment_date' => $firstPaymentDate,
                        'last_scheduled_payment_date' => $lastPaymentDate,
                        'application_date' => $now,
                        'approval_date' => $now,
                        'disbursement_date' => $now,
                        'status' => 'activo',
                        'original_loan_id' => (int) $loanId,
                        'admin_observations' => $loanRow['admin_observations'] ?? null,
                        'internal_observations' => $loanRow['internal_observations'] ?? null,
                        'finance_signatory' => $loanRow['finance_signatory'] ?? null,
                        'lender_signatory' => $loanRow['lender_signatory'] ?? null,
                        'requires_restructuring' => 0,
                        'created_by' => (int) $currentUser->id,
                    ]);

                    $newLoanId = (int) $db->lastInsertId();
                    if ($newLoanId <= 0) {
                        throw new RuntimeException('No fue posible crear el préstamo reestructurado.');
                    }

                    $paymentConfigInsertStatement->execute([
                        'loan_id' => $newLoanId,
                        'income_type_id' => $defaultIncomeTypeId,
                        'total_amount_to_deduct' => $restructuredPrincipal,
                        'number_of_installments' => (int) $newTermFortnights,
                        'amount_per_installment' => $restructuredPrincipal / max(1, (int) $newTermFortnights),
                        'interest_method' => 'simple_aleman',
                        'supporting_document_path' => $sourceConfig['supporting_document_path'] ?? null,
                        'document_status' => (string) ($sourceConfig['document_status'] ?? 'pendiente'),
                        'document_observations' => $sourceConfig['document_observations'] ?? null,
                        'document_validation_date' => $sourceConfig['document_validation_date'] ?? null,
                    ]);

                    foreach ($newScheduleRows as $row) {
                        $amortizationInsertStatement->execute([
                            'loan_id' => $newLoanId,
                            'payment_number' => $row->paymentNumber(),
                            'income_type_id' => $row->incomeTypeId(),
                            'scheduled_date' => $row->scheduledDate()->format('Y-m-d'),
                            'initial_balance' => $row->initialBalance()->amount(),
                            'principal' => $row->principal()->amount(),
                            'ordinary_interest' => $row->ordinaryInterest()->amount(),
                            'total_scheduled_payment' => $row->totalScheduledPayment()->amount(),
                            'final_balance' => $row->finalBalance()->amount(),
                            'payment_status' => 'pendiente',
                            'table_version' => 1,
                            'active' => 1,
                        ]);
                    }

                    $deactivatePendingRowsStatement->execute(['loan_id' => $loanId]);

                    $oldLoanUpdateStatement->execute([
                        'status' => 'reestructurado',
                        'outstanding_balance' => 0,
                        'total_liquidation_date' => $now,
                        'loan_id' => $loanId,
                    ]);

                    $restructuringInsertStatement->execute([
                        'original_loan_id' => $loanId,
                        'new_loan_id' => $newLoanId,
                        'reason' => $restructureReason,
                        'original_outstanding_balance' => $baseOutstandingBalance,
                        'pending_interest' => $pendingInterest,
                        'pending_default_interest' => $pendingDefaultInterest,
                        'new_total_amount' => $newTotalAmount,
                        'new_interest_rate' => (float) $newInterestRate,
                        'new_term_fortnights' => (int) $newTermFortnights,
                        'restructuring_date' => $now,
                        'authorized_by' => (int) $currentUser->id,
                        'observations' => $restructureObservations,
                    ]);

                    $eventInsertStatement->execute([
                        'loan_id' => (int) $loanId,
                        'event_type' => 'loan_restructured',
                        'description' => 'Reestructurado en el préstamo ' . $newFolio . ' (ID ' . $newLoanId . '). Motivo: ' . $auditReasonText . '.',
                        'event_date' => $now,
                    ]);

                    $eventInsertStatement->execute([
                        'loan_id' => $newLoanId,
                        'event_type' => 'loan_created_from_restructure',
                        'description' => 'Préstamo creado por reestructuración del préstamo ID ' . $loanId . '.',
                        'event_date' => $now,
                    ]);

                    $db->commit();
                } catch (\Throwable $transactionError) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    throw $transactionError;
                }

                header(
                    'Location: /portal/prestamos/detalle.php?id=' .
                    $newLoanId .
                    '&restructured=1&new_loan_id=' .
                    $newLoanId .
                    '&new_folio=' .
                    rawurlencode($newFolio),
                );
                exit();

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

$restructureDefaultAmount = null;
if (in_array($currentStatus, ["activo", "desembolsado"], true)) {
    try {
        $pendingSummaryStmt = $db->prepare(
            "SELECT
                COALESCE(SUM(principal), 0) AS pending_principal,
                COALESCE(SUM(ordinary_interest), 0) AS pending_interest,
                COALESCE(SUM(generated_default_interest), 0) AS pending_default_interest
             FROM loan_amortization
             WHERE loan_id = :loan_id
               AND active = 1
               AND payment_status IN ('pendiente', 'vencido')"
        );
        $pendingSummaryStmt->execute(['loan_id' => $loanId]);
        $pendingSummary = $pendingSummaryStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $pendingPrincipalForDefault = (float) ($pendingSummary['pending_principal'] ?? 0.0);
        $pendingInterestForDefault = (float) ($pendingSummary['pending_interest'] ?? 0.0);
        $pendingDefaultInterestForDefault = (float) ($pendingSummary['pending_default_interest'] ?? 0.0);
        $baseOutstandingForDefault = $pendingPrincipalForDefault > 0
            ? $pendingPrincipalForDefault
            : (float) ($loan['outstanding_balance'] ?? 0.0);

        $calculatedDefaultAmount = round(
            $baseOutstandingForDefault + $pendingInterestForDefault + $pendingDefaultInterestForDefault,
            2,
        );

        if ($calculatedDefaultAmount > 0) {
            $restructureDefaultAmount = $calculatedDefaultAmount;
        }
    } catch (\Throwable) {
        $restructureDefaultAmount = null;
    }
}

if ($restructureDefaultAmount === null) {
    $fallbackOutstandingAmount = round((float) ($loan['outstanding_balance'] ?? 0.0), 2);
    $restructureDefaultAmount = $fallbackOutstandingAmount > 0
        ? $fallbackOutstandingAmount
        : null;
}

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
    "restructureDefaultAmount" => $restructureDefaultAmount,
    "statusLabel" => $statusLabels[$currentStatus] ?? ucfirst($currentStatus),
    "statusBadge" => $statusBadges[$currentStatus] ?? "bg-light text-dark",
    "statusLabels" => $statusLabels,
    "statusBadges" => $statusBadges,
    "errors" => $errors,
    "success" => $success,
    "canReview" => $currentStatus === "solicitado",
    "canRestructure" => in_array($currentStatus, ["activo", "desembolsado"], true),
    "canHold" => $currentStatus === "solicitado",
    "canValidateDocs" => $currentStatus === "aprobado",
]);
