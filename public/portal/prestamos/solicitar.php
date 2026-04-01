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
        $term = (int) $request->input('term');
        $interestMethod = $request->input('interest_method');
        $disbursementDate = $request->input('disbursement_date');
        $firstPaymentDate = $request->input('first_payment_date');
        $paymentFrequency = $request->input('payment_frequency', 'quincenal');
        $paymentDay = (int) $request->input('payment_day', 15);
        $paymentMonthDay = (int) $request->input('payment_month_day', 15);
        $notes = $request->input('notes', '');
        $saveDraft = $request->input('save_draft') === '1';
        
        // Validate inputs
        if ($requestedAmount <= 0) {
            $errors[] = "El monto solicitado debe ser mayor a cero";
        }
        if ($term <= 0) {
            $errors[] = "El plazo debe ser mayor a cero";
        }
        if (!in_array($interestMethod, ['metodo_aleman', 'compuesto'])) {
            $errors[] = "Método de interés inválido";
        }
        if (empty($disbursementDate)) {
            $errors[] = "Debe especificar la fecha de desembolso";
        }
        if (empty($firstPaymentDate)) {
            $errors[] = "Debe especificar la fecha del primer pago";
        }
        
        if (empty($errors)) {
            $result = $submitLoanUseCase->execute([
                'user_id' => $currentUser->id,
                'requested_amount' => $requestedAmount,
                'term' => $term,
                'interest_method' => $interestMethod,
                'disbursement_date' => $disbursementDate,
                'first_payment_date' => $firstPaymentDate,
                'payment_frequency' => $paymentFrequency,
                'payment_day' => $paymentDay,
                'payment_month_day' => $paymentMonthDay,
                'notes' => $notes,
                'save_draft' => $saveDraft,
            ]);
            
            $loanId = $result['loan_id'];
            $simulation = $result['simulation'];
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
]);
