<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\UseCase\SubmitLoanApplicationUseCase;
use App\Modules\Loan\Domain\Repository\SaverUserRepositoryInterface;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$db = $container->get(\PDO::class);

$currentUser = $userContext->get();
$request = new FormRequest();

// Check if user is a saver (for interest rate calculation)
$saverUserRepository = $container->get(SaverUserRepositoryInterface::class);
$isSaver = $saverUserRepository->isSaver($currentUser->id);

$errors = [];
$success = false;
$loanId = null;
$simulation = null;
$draftState = null;
$isDraftEdit = false;
$saveDraft = false;

$draftId = filter_var($_GET['draft_id'] ?? $_POST['draft_id'] ?? '', FILTER_VALIDATE_INT);
if ($draftId !== false && $draftId > 0) {
    $draftStmt = $db->prepare(
        'SELECT loan_id, user_id, status, requested_amount, applied_interest_rate
         FROM loans
         WHERE loan_id = :loan_id
           AND user_id = :user_id
           AND deletion_date IS NULL'
    );
    $draftStmt->execute([
        'loan_id' => $draftId,
        'user_id' => (int) $currentUser->id,
    ]);

    $draftState = $draftStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

    if ($draftState === null) {
        $errors[] = 'No se encontró el borrador solicitado.';
    } elseif ((string) ($draftState['status'] ?? '') !== 'borrador') {
        $errors[] = 'Solo se pueden editar solicitudes en estatus borrador.';
        $draftState = null;
    } else {
        $isDraftEdit = true;
        $loanId = (int) $draftState['loan_id'];
    }
}

if ($request->method() === "POST") {
    try {
        $submitLoanUseCase = $container->get(SubmitLoanApplicationUseCase::class);
        $amortizationCalculator = $container->get(AmortizationCalculator::class);
        
        $requestedAmount = (float) $request->input('requested_amount');
        $incomeAmounts = $_POST['income_amounts'] ?? [];
        $incomeInstallments = $_POST['income_installments'] ?? [];
        $incomeLastDates = $_POST['income_last_dates'] ?? [];
        $notes = $request->input('notes', '');
        $saveDraft = $request->input('save_draft') === '1';

        // Get income types from DB to calculate fortnights
        $incomeTypesStmt = $db->query("SELECT * FROM cat_income_types WHERE active = 1");
        $allIncomeTypes = $incomeTypesStmt->fetchAll(\PDO::FETCH_ASSOC);
        $incomeTypesMap = array_column($allIncomeTypes, null, 'income_type_id');

        $paymentConfigs = [];
        $totalDistributed = 0;
        $missingDocumentForDiscount = false;

        $existingDraftDocsByType = [];
        if ($isDraftEdit && $draftState !== null) {
            $existingDocsStmt = $db->prepare(
                'SELECT income_type_id, supporting_document_path
                 FROM loan_payment_configuration
                 WHERE loan_id = :loan_id'
            );
            $existingDocsStmt->execute(['loan_id' => (int) $draftState['loan_id']]);
            foreach ($existingDocsStmt->fetchAll(\PDO::FETCH_ASSOC) as $existingDocRow) {
                $existingTypeId = (int) ($existingDocRow['income_type_id'] ?? 0);
                $existingPath = trim((string) ($existingDocRow['supporting_document_path'] ?? ''));
                if ($existingTypeId > 0 && $existingPath !== '') {
                    $existingDraftDocsByType[$existingTypeId] = $existingPath;
                }
            }
        }

        $startDate = new \DateTimeImmutable();
        $yearEnd = new \DateTimeImmutable($startDate->format('Y') . '-12-31');
        $maxFortnightsThisYear = 1;

        // Keep the same deadline logic used in the use case (must finish by Dec 31).
        for ($candidateFortnights = 1; $candidateFortnights <= 48; $candidateFortnights++) {
            $candidateLastDate = $amortizationCalculator->calculateLastPaymentDate($startDate, $candidateFortnights);
            if ($candidateLastDate > $yearEnd) {
                break;
            }
            $maxFortnightsThisYear = $candidateFortnights;
        }

        $buildPeriodicOptions = static function (\DateTimeImmutable $inicio, array $tipoInfo): array {
            $isPeriodic = (bool) ($tipoInfo['is_periodic'] ?? false);
            if (!$isPeriodic) {
                return [];
            }

            $frecuencia = max(1, (int) ($tipoInfo['frequency_days'] ?? 30));
            $diaPago = max(1, (int) ($tipoInfo['tentative_payment_day'] ?? 15));
            $fechaMaxima = new \DateTimeImmutable($inicio->format('Y') . '-11-15');

            if ($inicio > $fechaMaxima) {
                return [];
            }

            $opciones = [];
            $cursor = $inicio->setDate((int) $inicio->format('Y'), (int) $inicio->format('m'), 1);
            $guard = 0;

            while ($cursor <= $fechaMaxima && $guard < 36) {
                $year = (int) $cursor->format('Y');
                $month = (int) $cursor->format('m');
                $totalDiasMes = (int) $cursor->format('t');

                $fraccionMensual = $frecuencia / $totalDiasMes;
                $vecesEnMes = $fraccionMensual > 0 ? max(1, (int) round(1 / $fraccionMensual)) : 1;
                $diaBase = min(max(1, $diaPago), $totalDiasMes);
                $saltoDias = max(1, (int) round($totalDiasMes / $vecesEnMes));

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

        foreach ($incomeAmounts as $typeId => $amount) {
            $amount = (float)$amount;
            if ($amount > 0) {
                // Determine document upload (mocked path for now or if file upload is implemented)
                $documentPath = null;
                $uploadError = $_FILES['income_documents']['error'][$typeId] ?? UPLOAD_ERR_NO_FILE;
                if (isset($_FILES['income_documents']['tmp_name'][$typeId]) && $uploadError === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../../uploads/solicitudes/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                    $filename = time() . '_' . basename($_FILES['income_documents']['name'][$typeId]);
                    if (move_uploaded_file($_FILES['income_documents']['tmp_name'][$typeId], $uploadDir . $filename)) {
                        $documentPath = '/uploads/solicitudes/' . $filename;
                    }
                } elseif (isset($existingDraftDocsByType[(int) $typeId])) {
                    $documentPath = $existingDraftDocsByType[(int) $typeId];
                }

                if ($documentPath === null || $documentPath === '') {
                    $missingDocumentForDiscount = true;
                }

                $typeInfo = $incomeTypesMap[$typeId] ?? null;
                $isPeriodic = $typeInfo && $typeInfo['is_periodic'];
                
                $fortnights = 1;
                $interestMethod = 'compuesto'; // default for unique payment
                
                if ($isPeriodic && !empty($incomeLastDates[$typeId])) {
                    $opcionesFechas = $buildPeriodicOptions($startDate, (array) $typeInfo);
                    $cantidadSeleccionada = max(1, (int) ($incomeInstallments[$typeId] ?? 1));
                    $fechaSeleccionada = (string) ($incomeLastDates[$typeId] ?? '');

                    if ($opcionesFechas !== []) {
                        $indiceSeleccionado = array_search($fechaSeleccionada, $opcionesFechas, true);

                        if ($indiceSeleccionado !== false) {
                            $cantidadSeleccionada = min($cantidadSeleccionada, $indiceSeleccionado + 1);
                        }

                        $fortnights = max(1, min($cantidadSeleccionada, count($opcionesFechas)));
                    } else {
                        // Backward-compatible fallback when there are no periodic options.
                        try {
                            $estimatedLastPaymentDate = new \DateTimeImmutable($fechaSeleccionada);
                            if ($estimatedLastPaymentDate <= $startDate) {
                                $estimatedFortnights = 1;
                            } else {
                                $daysUntilEstimatedLastPayment = (int) $startDate->diff($estimatedLastPaymentDate)->days;
                                $estimatedFortnights = max(1, (int) ceil(($daysUntilEstimatedLastPayment + 1) / 15));
                            }

                            $fortnights = min($estimatedFortnights, $maxFortnightsThisYear);
                        } catch (\Exception) {
                            $fortnights = 1;
                        }
                    }

                    $interestMethod = 'simple_aleman';
                }

                $paymentConfigs[] = [
                    'income_type_id' => $typeId,
                    'amount' => $amount,
                    'fortnights' => (int)$fortnights,
                    'interest_method' => $interestMethod,
                    'document_path' => $documentPath
                ];
                $totalDistributed += $amount;
            }
        }

        // Validate inputs
        if ($requestedAmount <= 0) {
            $errors[] = "El monto solicitado debe ser mayor a cero";
        }
        if (round($totalDistributed, 2) !== round($requestedAmount, 2)) {
            $errors[] = "El monto distribuido debe coincidir exactamente con el monto solicitado.";
        }
        if ($missingDocumentForDiscount) {
            $errors[] = "Todos los tipos de descuento deben incluir obligatoriamente su comprobante.";
        }
        if (empty($paymentConfigs)) {
            $errors[] = "Debe asignar el pago a al menos una forma de descuento.";
        }

        if (empty($errors)) {
            if ($isDraftEdit && $draftState !== null) {
                $db->beginTransaction();
                try {
                    $termFortnights = (int) array_sum(array_column($paymentConfigs, 'fortnights'));

                    $updateDraftStmt = $db->prepare(
                        'UPDATE loans
                         SET requested_amount = :requested_amount,
                             term_fortnights = :term_fortnights
                         WHERE loan_id = :loan_id
                           AND user_id = :user_id
                           AND status = :status
                           AND deletion_date IS NULL'
                    );
                    $updateDraftStmt->execute([
                        'requested_amount' => $requestedAmount,
                        'term_fortnights' => $termFortnights,
                        'loan_id' => (int) $draftState['loan_id'],
                        'user_id' => (int) $currentUser->id,
                        'status' => 'borrador',
                    ]);

                    if ($updateDraftStmt->rowCount() === 0) {
                        $verifyDraftStmt = $db->prepare(
                            'SELECT 1
                             FROM loans
                             WHERE loan_id = :loan_id
                               AND user_id = :user_id
                               AND status = :status
                               AND deletion_date IS NULL'
                        );
                        $verifyDraftStmt->execute([
                            'loan_id' => (int) $draftState['loan_id'],
                            'user_id' => (int) $currentUser->id,
                            'status' => 'borrador',
                        ]);

                        if ($verifyDraftStmt->fetchColumn() === false) {
                            throw new RuntimeException('No fue posible actualizar el borrador.');
                        }
                    }

                    $deleteConfigsStmt = $db->prepare('DELETE FROM loan_payment_configuration WHERE loan_id = :loan_id');
                    $deleteConfigsStmt->execute(['loan_id' => (int) $draftState['loan_id']]);

                    $insertConfigStmt = $db->prepare(
                        'INSERT INTO loan_payment_configuration (
                            loan_id,
                            income_type_id,
                            total_amount_to_deduct,
                            number_of_installments,
                            amount_per_installment,
                            interest_method,
                            supporting_document_path,
                            document_status
                        ) VALUES (
                            :loan_id,
                            :income_type_id,
                            :total_amount_to_deduct,
                            :number_of_installments,
                            :amount_per_installment,
                            :interest_method,
                            :supporting_document_path,
                            :document_status
                        )'
                    );

                    foreach ($paymentConfigs as $config) {
                        $installments = max(1, (int) $config['fortnights']);
                        $totalAmount = (float) $config['amount'];

                        $insertConfigStmt->execute([
                            'loan_id' => (int) $draftState['loan_id'],
                            'income_type_id' => (int) $config['income_type_id'],
                            'total_amount_to_deduct' => $totalAmount,
                            'number_of_installments' => $installments,
                            'amount_per_installment' => $totalAmount / $installments,
                            'interest_method' => (string) ($config['interest_method'] ?? 'simple_aleman'),
                            'supporting_document_path' => $config['document_path'] ?? null,
                            'document_status' => 'pendiente',
                        ]);
                    }

                    $db->commit();
                    $loanId = (int) $draftState['loan_id'];
                } catch (\Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $e;
                }

                if (!$saveDraft) {
                    $submitLoanUseCase->submit($loanId);
                }

                $success = true;
            } else {
                $result = $submitLoanUseCase->execute(
                    $currentUser->id,
                    $currentUser->role,
                    new \App\Modules\Loan\Domain\ValueObject\Money($requestedAmount),
                    $paymentConfigs
                );

                $loanId = $result['loan_id'];

                if (!$saveDraft) {
                    $submitLoanUseCase->submit($loanId);
                }

                $simulation = $result['amortization_schedule'];
                $success = true;
            }
        }
    } catch (InvalidLoanStatusException $e) {
        $errors[] = $e->getMessage();
    } catch (\Exception $e) {
        $errors[] = "Error al procesar la solicitud: " . $e->getMessage();
    }
}

if ($success && !$saveDraft) {
    header('Location: /portal/prestamos/activos.php');
    exit;
}

// Get current date for defaults
$today = date('Y-m-d');
$nextMonth = date('Y-m-d', strtotime('+1 month'));

// Get income types
$incomeTypesStmt = $db->query("SELECT * FROM cat_income_types WHERE active = 1");
$incomeTypes = $incomeTypesStmt->fetchAll(\PDO::FETCH_OBJ);
$incomeTypesJson = json_encode($incomeTypes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$draftInitialJson = null;
if ($isDraftEdit && $draftState !== null) {
    $draftConfigsStmt = $db->prepare(
        'SELECT income_type_id, total_amount_to_deduct, number_of_installments
         FROM loan_payment_configuration
         WHERE loan_id = :loan_id
         ORDER BY payment_config_id ASC'
    );
    $draftConfigsStmt->execute(['loan_id' => (int) $draftState['loan_id']]);
    $draftConfigs = $draftConfigsStmt->fetchAll(\PDO::FETCH_ASSOC);

    $draftDiscounts = [];
    foreach ($draftConfigs as $config) {
        $draftDiscounts[] = [
            'monto' => (float) ($config['total_amount_to_deduct'] ?? 0),
            'tipoId' => (int) ($config['income_type_id'] ?? 0),
            'cantidad' => (int) ($config['number_of_installments'] ?? 0),
            'fechaPago' => '',
        ];
    }

    $draftInitialJson = json_encode([
        'interestRate' => (float) ($draftState['applied_interest_rate'] ?? 0),
        'discounts' => $draftDiscounts,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$renderer->render("./solicitar.latte", [
    'user' => $currentUser,
    'is_saver' => $isSaver,
    'errors' => $errors,
    'success' => $success,
    'loan_id' => $loanId,
    'simulation' => $simulation,
    'today' => $today,
    'next_month' => $nextMonth,
    'old_input' => $request->all(),
    'income_types' => $incomeTypes,
    'income_types_json' => $incomeTypesJson,
    'is_draft_edit' => $isDraftEdit,
    'draft_id' => $isDraftEdit ? (int) $draftState['loan_id'] : null,
    'draft_initial_json' => $draftInitialJson,
]);
