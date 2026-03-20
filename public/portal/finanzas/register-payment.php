<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

// Verify user is Finanzas
if ($user->role !== RoleEnum::Finanzas) {
    JsonResponse::forbidden("Access denied")->send();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::methodNotAllowed()->send();
    exit;
}

$request = new FormRequest();

try {
    // Get form data
    $amortizationId = $request->input('amortization_id');
    $actualPaidAmount = $request->input('actual_paid_amount');
    $actualPaymentDate = $request->input('actual_payment_date');

    // Validate required fields
    if (!$amortizationId || $actualPaidAmount === null || !$actualPaymentDate) {
        JsonResponse::badRequest('Faltan datos requeridos para registrar el pago')->send();
        exit;
    }

    // Validate amount
    $actualPaidAmount = (float) $actualPaidAmount;
    if ($actualPaidAmount <= 0) {
        JsonResponse::badRequest('El monto debe ser mayor a 0')->send();
        exit;
    }

    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $actualPaymentDate);
    if ($dateObj === false) {
        JsonResponse::badRequest('Fecha inválida')->send();
        exit;
    }

    // Get database connection
    /** @var PDO $pdo */
    $pdo = $container->get(PDO::class);

    // Start transaction
    $pdo->beginTransaction();

    // Verify amortization exists and belongs to a valid loan
    $stmt = $pdo->prepare('
        SELECT la.amortization_id, la.scheduled_date, la.total_scheduled_payment, 
               la.payment_status, la.days_overdue, la.ordinary_interest
        FROM loan_amortization la
        WHERE la.amortization_id = ? AND la.active = 1
    ');
    $stmt->execute([$amortizationId]);
    $amortization = $stmt->fetch();

    if (!$amortization) {
        $pdo->rollBack();
        JsonResponse::notFound('Cuota no encontrada')->send();
        exit;
    }

    // Calculate overdue interest if applicable
    $generatedDefaultInterest = 0.00;
    $newDaysOverdue = 0;
    $paymentStatus = 'pagado';

    if ($amortization['payment_status'] === 'pendiente') {
        $scheduledDate = new DateTime($amortization['scheduled_date']);
        $paymentDate = new DateTime($actualPaymentDate);

        if ($paymentDate > $scheduledDate) {
            $newDaysOverdue = $paymentDate->diff($scheduledDate)->days;
            // Default interest calculation: 1% per month of ordinary interest
            $monthlyRate = 0.01;
            $generatedDefaultInterest = round($amortization['ordinary_interest'] * $monthlyRate * ($newDaysOverdue / 30), 2);
        }
    }

    // Handle payment receipt file
    $paymentReceiptPath = null;
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/../../uploads/payments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'payment_' . $amortizationId . '_' . time() . '.' . pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $uploadPath)) {
            $paymentReceiptPath = 'payments/' . $filename;
        }
    }

    // Update loan_amortization
    $stmt = $pdo->prepare('
        UPDATE loan_amortization
        SET actual_payment_date = ?,
            actual_paid_amount = ?,
            payment_status = ?,
            paid_by = ?,
            payment_receipt = ?,
            days_overdue = ?,
            generated_default_interest = ?
        WHERE amortization_id = ?
    ');

    $stmt->execute([
        $actualPaymentDate . ' ' . date('H:i:s'),
        $actualPaidAmount,
        $paymentStatus,
        $user->id,
        $paymentReceiptPath,
        $newDaysOverdue,
        $generatedDefaultInterest,
        $amortizationId,
    ]);

    $pdo->commit();

    JsonResponse::created([
        'success' => true,
        'message' => 'Pago registrado correctamente',
        'days_overdue' => $newDaysOverdue,
        'default_interest' => $generatedDefaultInterest,
    ])->send();
    exit;

} catch (\Throwable $th) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    JsonResponse::serverError("Error al registrar el pago: " . $th->getMessage())->send();
    exit;
}
