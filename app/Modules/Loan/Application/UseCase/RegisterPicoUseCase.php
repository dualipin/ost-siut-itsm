<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\LoanEventLogger;

/**
 * Use Case: Register Pico (Missed Payment)
 * 
 * Business Flow (per specification):
 * 1. User missed a payment (pico)
 * 2. Calculate default interest: balance × daily_default_rate × days_overdue
 * 3. Add default interest to outstanding balance
 * 4. Regenerate entire amortization table with new balance
 * 5. Delete old unpaid rows
 * 6. Mark original missed row as 'pico'
 * 7. Log pico event
 * 
 * Note: Default interest rate is 2% per spec
 */
final readonly class RegisterPicoUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private AmortizationCalculator $amortizationCalculator,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * @param array{
     *   loan_id: int,
     *   amortization_row_id: int,
     *   days_overdue: int,
     *   registered_by: int,
     *   notes?: string
     * } $data
     * @throws LoanNotFoundException
     * @throws InvalidLoanStatusException
     * @return array{new_balance: float, default_interest: float}
     */
    public function execute(array $data): array
    {
        $loan = $this->loanRepository->findById($data['loan_id']);
        if (!$loan) {
            throw new LoanNotFoundException("Loan with ID {$data['loan_id']} not found");
        }

        if ($loan->status !== LoanStatusEnum::ACTIVE) {
            throw new InvalidLoanStatusException(
                "Loan must be in 'activo' status to register picos. Current status: {$loan->status->value}"
            );
        }

        // Get the missed row
        $missedRow = $this->amortizationRepository->findById($data['amortization_row_id']);
        if (!$missedRow || $missedRow->loanId !== $loan->id) {
            throw new InvalidLoanStatusException("Invalid amortization row for this loan");
        }

        // Calculate default interest: 2% annual = 0.02 / 365 daily
        $defaultRate = 0.02 / 365;
        $defaultInterest = $loan->outstandingBalance * $defaultRate * $data['days_overdue'];

        // Update outstanding balance
        $newBalance = $loan->outstandingBalance + $defaultInterest;

        // Mark missed row as 'pico'
        $this->amortizationRepository->update($missedRow->id, [
            'payment_status' => PaymentStatusEnum::PICO->value,
            'default_interest' => $defaultInterest,
            'days_overdue' => $data['days_overdue'],
        ]);

        // Delete all unpaid rows after the pico
        $unpaidRows = $this->amortizationRepository->findUnpaidByLoanId($loan->id);
        foreach ($unpaidRows as $row) {
            if ($row->rowNumber > $missedRow->rowNumber) {
                $this->amortizationRepository->delete($row->id);
            }
        }

        // Regenerate amortization table with new balance
        $paymentConfig = $loan->paymentConfiguration;
        
        // Calculate remaining term (original term - paid rows)
        $paidRows = $this->amortizationRepository->findPaidByLoanId($loan->id);
        $remainingTerm = $loan->term - count($paidRows);

        $newRows = $this->amortizationCalculator->calculateGermanSimple(
            $newBalance,
            $loan->interestRate->value,
            $remainingTerm,
            $paymentConfig->paymentDay,
            $paymentConfig->paymentMonthDay,
            new \DateTime($paymentConfig->firstPaymentDate)
        );

        // Insert new rows starting from next row number
        $startingRowNumber = $missedRow->rowNumber + 1;
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

        // Update loan
        $this->loanRepository->update($loan->id, [
            'outstanding_balance' => $newBalance,
            'term' => count($paidRows) + count($newRows),
        ]);

        // Log event
        $this->eventLogger->log(
            $loan->id,
            'pico_registered',
            $data['registered_by'],
            [
                'amortization_row' => $missedRow->rowNumber,
                'days_overdue' => $data['days_overdue'],
                'default_interest' => $defaultInterest,
                'new_balance' => $newBalance,
                'rows_regenerated' => count($newRows),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return [
            'new_balance' => $newBalance,
            'default_interest' => $defaultInterest,
        ];
    }
}
