<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ReceiptRepositoryInterface;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\Enum\ReceiptTypeEnum;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Domain\Exception\InvalidPaymentException;
use App\Modules\Loan\Application\Service\LoanEventLogger;

/**
 * Use Case: Register Regular Payment
 * 
 * Business Flow:
 * 1. Loan must be in 'activo' status
 * 2. Find the next unpaid amortization row
 * 3. Validate payment amount matches expected amount
 * 4. Mark row as paid
 * 5. Generate receipt
 * 6. Update loan outstanding balance
 * 7. If all rows paid, transition loan to 'liquidado'
 */
final readonly class RegisterPaymentUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private ReceiptRepositoryInterface $receiptRepository,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * @param array{
     *   loan_id: int,
     *   amount: float,
     *   payment_date: string,
     *   payment_method: string,
     *   reference?: string,
     *   registered_by: int,
     *   notes?: string
     * } $data
     * @throws LoanNotFoundException
     * @throws InvalidLoanStatusException
     * @throws InvalidPaymentException
     * @return array{receipt_id: int, remaining_balance: float}
     */
    public function execute(array $data): array
    {
        $loan = $this->loanRepository->findById($data['loan_id']);
        if (!$loan) {
            throw new LoanNotFoundException("Loan with ID {$data['loan_id']} not found");
        }

        // Only active loans can receive payments
        if ($loan->status !== LoanStatusEnum::ACTIVE) {
            throw new InvalidLoanStatusException(
                "Loan must be in 'activo' status to register payments. Current status: {$loan->status->value}"
            );
        }

        // Find next unpaid amortization row
        $nextRow = $this->amortizationRepository->findNextUnpaidByLoanId($loan->id);
        if (!$nextRow) {
            throw new InvalidPaymentException("No pending payments found for this loan");
        }

        // Validate payment amount
        $expectedAmount = $nextRow->totalPayment;
        $tolerance = 0.01; // 1 cent tolerance for floating point
        
        if (abs($data['amount'] - $expectedAmount) > $tolerance) {
            throw new InvalidPaymentException(
                "Payment amount mismatch. Expected: {$expectedAmount}, Received: {$data['amount']}"
            );
        }

        // Mark row as paid
        $this->amortizationRepository->update($nextRow->id, [
            'payment_status' => PaymentStatusEnum::PAID->value,
            'paid_at' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['reference'] ?? null,
        ]);

        // Generate receipt
        $receiptId = $this->receiptRepository->create([
            'loan_id' => $loan->id,
            'amortization_id' => $nextRow->id,
            'receipt_type' => ReceiptTypeEnum::REGULAR_PAYMENT->value,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'issued_by' => $data['registered_by'],
            'notes' => $data['notes'] ?? null,
        ]);

        // Update loan outstanding balance
        $newBalance = $loan->outstandingBalance - $nextRow->principal;
        $this->loanRepository->update($loan->id, [
            'outstanding_balance' => $newBalance,
            'last_payment_date' => $data['payment_date'],
        ]);

        // Check if all rows are paid
        $unpaidRows = $this->amortizationRepository->findUnpaidByLoanId($loan->id);
        if (count($unpaidRows) === 0) {
            $this->loanRepository->update($loan->id, [
                'status' => LoanStatusEnum::SETTLED->value,
                'settled_at' => date('Y-m-d H:i:s'),
                'outstanding_balance' => 0,
            ]);

            $this->eventLogger->log(
                $loan->id,
                'loan_settled',
                $data['registered_by'],
                ['final_payment_date' => $data['payment_date']]
            );
        }

        // Log payment event
        $this->eventLogger->log(
            $loan->id,
            'payment_registered',
            $data['registered_by'],
            [
                'amortization_row' => $nextRow->rowNumber,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'remaining_balance' => $newBalance,
            ]
        );

        return [
            'receipt_id' => $receiptId,
            'remaining_balance' => $newBalance,
        ];
    }
}
