<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ExtraordinaryPaymentRepositoryInterface;
use App\Modules\Loan\Domain\Repository\ReceiptRepositoryInterface;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\Enum\PaymentTypeEnum;
use App\Modules\Loan\Domain\Enum\ReceiptTypeEnum;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Domain\Exception\InvalidPaymentException;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\LoanEventLogger;

/**
 * Use Case: Register Extraordinary Payment
 * 
 * Business Flow (per specification):
 * 1. Types: ADVANCE (pay next N rows), PRINCIPAL (reduce balance), TOTAL (settle loan)
 * 2. For ADVANCE: Mark next N rows as paid, update balance
 * 3. For PRINCIPAL: Reduce balance, regenerate amortization table
 * 4. For TOTAL: Pay all remaining, settle loan
 * 5. Generate receipt
 * 6. Log event
 */
final readonly class RegisterExtraordinaryPaymentUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private ExtraordinaryPaymentRepositoryInterface $extraordinaryPaymentRepository,
        private ReceiptRepositoryInterface $receiptRepository,
        private AmortizationCalculator $amortizationCalculator,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * @param array{
     *   loan_id: int,
     *   payment_type: string,
     *   amount: float,
     *   payment_date: string,
     *   payment_method: string,
     *   reference?: string,
     *   registered_by: int,
     *   notes?: string,
     *   rows_to_advance?: int
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

        if ($loan->status !== LoanStatusEnum::ACTIVE) {
            throw new InvalidLoanStatusException(
                "Loan must be in 'activo' status. Current status: {$loan->status->value}"
            );
        }

        $paymentType = PaymentTypeEnum::from($data['payment_type']);
        $newBalance = $loan->outstandingBalance;

        // Record extraordinary payment
        $extraordinaryPaymentId = $this->extraordinaryPaymentRepository->create([
            'loan_id' => $loan->id,
            'payment_type' => $paymentType->value,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'registered_by' => $data['registered_by'],
            'applied_to_principal' => 0, // Will update below
            'applied_to_interest' => 0,
            'notes' => $data['notes'] ?? null,
        ]);

        match ($paymentType) {
            PaymentTypeEnum::ADVANCE => $this->handleAdvancePayment(
                $loan,
                $data,
                $extraordinaryPaymentId,
                $newBalance
            ),
            PaymentTypeEnum::PRINCIPAL => $this->handlePrincipalPayment(
                $loan,
                $data,
                $extraordinaryPaymentId,
                $newBalance
            ),
            PaymentTypeEnum::TOTAL => $this->handleTotalPayment(
                $loan,
                $data,
                $extraordinaryPaymentId,
                $newBalance
            ),
        };

        // Generate receipt
        $receiptId = $this->receiptRepository->create([
            'loan_id' => $loan->id,
            'receipt_type' => ReceiptTypeEnum::EXTRAORDINARY_PAYMENT->value,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'issued_by' => $data['registered_by'],
            'notes' => "Extraordinary payment: {$paymentType->value}",
        ]);

        // Get updated balance
        $loan = $this->loanRepository->findById($loan->id);
        
        return [
            'receipt_id' => $receiptId,
            'remaining_balance' => $loan->outstandingBalance,
        ];
    }

    private function handleAdvancePayment($loan, array $data, int $extraordinaryPaymentId, float &$balance): void
    {
        $rowsToAdvance = $data['rows_to_advance'] ?? 1;
        $unpaidRows = $this->amortizationRepository->findUnpaidByLoanId($loan->id);
        
        if (count($unpaidRows) < $rowsToAdvance) {
            throw new InvalidPaymentException("Not enough unpaid rows to advance");
        }

        $totalRequired = 0;
        $totalPrincipal = 0;
        $totalInterest = 0;

        for ($i = 0; $i < $rowsToAdvance; $i++) {
            $totalRequired += $unpaidRows[$i]->totalPayment;
            $totalPrincipal += $unpaidRows[$i]->principal;
            $totalInterest += $unpaidRows[$i]->interest;
        }

        if (abs($data['amount'] - $totalRequired) > 0.01) {
            throw new InvalidPaymentException(
                "Amount mismatch. Required for {$rowsToAdvance} rows: {$totalRequired}"
            );
        }

        // Mark rows as paid
        for ($i = 0; $i < $rowsToAdvance; $i++) {
            $this->amortizationRepository->update($unpaidRows[$i]->id, [
                'payment_status' => PaymentStatusEnum::PAID->value,
                'paid_at' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['reference'] ?? null,
            ]);
        }

        $balance -= $totalPrincipal;

        $this->extraordinaryPaymentRepository->update($extraordinaryPaymentId, [
            'applied_to_principal' => $totalPrincipal,
            'applied_to_interest' => $totalInterest,
        ]);

        $this->loanRepository->update($loan->id, [
            'outstanding_balance' => $balance,
            'last_payment_date' => $data['payment_date'],
        ]);

        $this->eventLogger->log(
            $loan->id,
            'extraordinary_payment_advance',
            $data['registered_by'],
            [
                'rows_advanced' => $rowsToAdvance,
                'amount' => $data['amount'],
                'new_balance' => $balance,
            ]
        );
    }

    private function handlePrincipalPayment($loan, array $data, int $extraordinaryPaymentId, float &$balance): void
    {
        if ($data['amount'] > $loan->outstandingBalance) {
            throw new InvalidPaymentException("Payment exceeds outstanding balance");
        }

        $balance -= $data['amount'];

        $this->extraordinaryPaymentRepository->update($extraordinaryPaymentId, [
            'applied_to_principal' => $data['amount'],
        ]);

        // Regenerate amortization table
        $unpaidRows = $this->amortizationRepository->findUnpaidByLoanId($loan->id);
        $paymentConfig = $loan->paymentConfiguration;

        // Delete unpaid rows
        foreach ($unpaidRows as $row) {
            $this->amortizationRepository->delete($row->id);
        }

        $paidRows = $this->amortizationRepository->findPaidByLoanId($loan->id);
        $remainingTerm = $loan->term - count($paidRows);

        $newRows = $this->amortizationCalculator->calculateGermanSimple(
            $balance,
            $loan->interestRate->value,
            $remainingTerm,
            $paymentConfig->paymentDay,
            $paymentConfig->paymentMonthDay,
            new \DateTime($paymentConfig->firstPaymentDate)
        );

        $startingRowNumber = count($paidRows) + 1;
        foreach ($newRows as $index => $rowData) {
            $this->amortizationRepository->create([
                'loan_id' => $loan->id,
                'row_number' => $startingRowNumber + $index,
                'payment_date' => $rowData['payment_date'],
                'beginning_balance' => $rowData['beginning_balance'],
                'principal' => $rowData['principal'],
                'interest' => $rowData['interest'],
                'total_payment' => $rowData['total_payment'],
                'ending_balance' => $rowData['ending_balance'],
                'payment_status' => PaymentStatusEnum::PENDING->value,
                'days_in_period' => $rowData['days_in_period'],
            ]);
        }

        $this->loanRepository->update($loan->id, [
            'outstanding_balance' => $balance,
            'last_payment_date' => $data['payment_date'],
        ]);

        $this->eventLogger->log(
            $loan->id,
            'extraordinary_payment_principal',
            $data['registered_by'],
            [
                'amount' => $data['amount'],
                'new_balance' => $balance,
                'rows_regenerated' => count($newRows),
            ]
        );
    }

    private function handleTotalPayment($loan, array $data, int $extraordinaryPaymentId, float &$balance): void
    {
        $unpaidRows = $this->amortizationRepository->findUnpaidByLoanId($loan->id);
        $totalRequired = array_sum(array_map(fn($row) => $row->totalPayment, $unpaidRows));

        if (abs($data['amount'] - $totalRequired) > 0.01) {
            throw new InvalidPaymentException(
                "Amount mismatch. Total required: {$totalRequired}"
            );
        }

        $totalPrincipal = array_sum(array_map(fn($row) => $row->principal, $unpaidRows));
        $totalInterest = array_sum(array_map(fn($row) => $row->interest, $unpaidRows));

        // Mark all rows as paid
        foreach ($unpaidRows as $row) {
            $this->amortizationRepository->update($row->id, [
                'payment_status' => PaymentStatusEnum::PAID->value,
                'paid_at' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['reference'] ?? null,
            ]);
        }

        $balance = 0;

        $this->extraordinaryPaymentRepository->update($extraordinaryPaymentId, [
            'applied_to_principal' => $totalPrincipal,
            'applied_to_interest' => $totalInterest,
        ]);

        $this->loanRepository->update($loan->id, [
            'outstanding_balance' => 0,
            'status' => LoanStatusEnum::SETTLED->value,
            'settled_at' => date('Y-m-d H:i:s'),
            'last_payment_date' => $data['payment_date'],
        ]);

        $this->eventLogger->log(
            $loan->id,
            'extraordinary_payment_total',
            $data['registered_by'],
            [
                'amount' => $data['amount'],
                'principal_paid' => $totalPrincipal,
                'interest_paid' => $totalInterest,
            ]
        );

        $this->eventLogger->log(
            $loan->id,
            'loan_settled',
            $data['registered_by'],
            ['settlement_date' => $data['payment_date']]
        );
    }
}
