<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
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

$currentUser = $userContext->get();
$request = new FormRequest();

// Check if user is a saver (for interest rate calculation)
$saverUserRepository = $container->get(SaverUserRepositoryInterface::class);
$isSaver = $saverUserRepository->isSaver($currentUser->id);

$errors = [];
$success = false;
$loanId = null;
$simulation = null;

if ($request->method() === "POST") {
    try {
        $submitLoanUseCase = $container->get(SubmitLoanApplicationUseCase::class);
        
        $requestedAmount = (float) $request->input('requested_amount');
        $incomeAmounts = $_POST['income_amounts'] ?? [];
        $incomeLastDates = $_POST['income_last_dates'] ?? [];
        $notes = $request->input('notes', '');
        $saveDraft = $request->input('save_draft') === '1';

        // Get income types from DB to calculate fortnights
        $db = $container->get(\PDO::class);
        $incomeTypesStmt = $db->query("SELECT * FROM cat_income_types WHERE active = 1");
        $allIncomeTypes = $incomeTypesStmt->fetchAll(\PDO::FETCH_ASSOC);
        $incomeTypesMap = array_column($allIncomeTypes, null, 'income_type_id');

        $paymentConfigs = [];
        $totalDistributed = 0;

        foreach ($incomeAmounts as $typeId => $amount) {
            $amount = (float)$amount;
            if ($amount > 0) {
                // Determine document upload (mocked path for now or if file upload is implemented)
                $documentPath = null;
                if (isset($_FILES['income_documents']['tmp_name'][$typeId]) && $_FILES['income_documents']['error'][$typeId] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../../uploads/solicitudes/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                    $filename = time() . '_' . basename($_FILES['income_documents']['name'][$typeId]);
                    if (move_uploaded_file($_FILES['income_documents']['tmp_name'][$typeId], $uploadDir . $filename)) {
                        $documentPath = '/uploads/solicitudes/' . $filename;
                    }
                }

                $typeInfo = $incomeTypesMap[$typeId] ?? null;
                $isPeriodic = $typeInfo && $typeInfo['is_periodic'];
                $freqDays = $typeInfo ? ($typeInfo['frequency_days'] ?: 0) : 0;
                
                $fortnights = 1;
                $interestMethod = 'compuesto'; // default for unique payment
                
                if ($isPeriodic && !empty($incomeLastDates[$typeId])) {
                     // calculate approximate periods (fortnights)
                     $lastDate = new \DateTime($incomeLastDates[$typeId]);
                     $today = new \DateTime();
                     $diff = $today->diff($lastDate)->days;
                     $fortnights = $freqDays > 0 ? max(1, ceil($diff / $freqDays)) : 1;
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
        if (empty($paymentConfigs)) {
            $errors[] = "Debe asignar el pago a al menos una forma de descuento.";
        }

        if (empty($errors)) {
            $result = $submitLoanUseCase->execute(
                $currentUser->id,
                $currentUser->role,
                new \App\Modules\Loan\Domain\ValueObject\Money($requestedAmount),
                $paymentConfigs
            );

            $loanId = $result['loan_id'];
            $simulation = $result['amortization_schedule'];
            $success = true;

            // Redirect to loan details or show success message
            if (!$saveDraft) {
                header("Location: /portal/prestamos/detalle.php?id={$loanId}&success=1");
                exit;
            }
        }
    } catch (InvalidLoanStatusException $e) {
        $errors[] = $e->getMessage();
    } catch (\Exception $e) {
        $errors[] = "Error al procesar la solicitud: " . $e->getMessage();
    }
}

// Get current date for defaults
$today = date('Y-m-d');
$nextMonth = date('Y-m-d', strtotime('+1 month'));

// Get income types
$db = $container->get(\PDO::class);
$incomeTypesStmt = $db->query("SELECT * FROM cat_income_types WHERE active = 1");
$incomeTypes = $incomeTypesStmt->fetchAll(\PDO::FETCH_OBJ);

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
]);
