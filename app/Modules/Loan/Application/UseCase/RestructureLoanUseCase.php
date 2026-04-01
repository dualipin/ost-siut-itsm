<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRestructuringRepositoryInterface;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Enum\PaymentStatusEnum;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\FolioGenerator;
use App\Modules\Loan\Application\Service\LoanEventLogger;

/**
 * Use Case: Restructure Loan
 * 
 * Business Flow (per specification):
 * 1. Close original loan (status = 'reestructurado')
 * 2. Calculate new balance (original balance + accumulated interest)
 * 3. Create new loan with new terms
 * 4. Generate new amortization table
 * 5. Record restructuring in loan_restructurings table
 * 6. Log events for both loans
 */
final readonly class RestructureLoanUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private PaymentConfigRepositoryInterface $paymentConfigRepository,
        private LoanRestructuringRepositoryInterface $restructuringRepository,
        private AmortizationCalculator $amortizationCalculator,
        private FolioGenerator $folioGenerator,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * @param array{
     *   original_loan_id: int,
     *   new_term: int,
     *   new_interest_rate: float,
     *   restructure_date: string,
     *   first_payment_date: string,
     *   payment_day: int,
     *   payment_month_day: int,
     *   reason: string,
     *   approved_by: int,
     *   notes?: string
     * } $data
     * @throws LoanNotFoundException
     * @throws InvalidLoanStatusException
     * @return array{new_loan_id: int, new_folio: string, new_balance: float}
     */
    public function execute(array $data): array
    {
        $originalLoan = $this->loanRepository->findById($data['original_loan_id']);
        if (!$originalLoan) {
            throw new LoanNotFoundException("Loan with ID {$data['original_loan_id']} not found");
        }

        if ($originalLoan->status !== LoanStatusEnum::ACTIVE) {
            throw new InvalidLoanStatusException(
                "Only active loans can be restructured. Current status: {$originalLoan->status->value}"
            );
        }

        // Calculate new balance (outstanding balance + accumulated unpaid interest)
        $unpaidRows = $this->amortizationRepository->findUnpaidByLoanId($originalLoan->id);
        $accumulatedInterest = array_sum(array_map(fn($row) => $row->interest, $unpaidRows));
        $newBalance = $originalLoan->outstandingBalance + $accumulatedInterest;

        // Generate folio for new loan
        $newFolio = $this->folioGenerator->generate();

        // Create new loan
        $newLoanId = $this->loanRepository->create([
            'user_id' => $originalLoan->userId,
            'folio' => $newFolio,
            'requested_amount' => $newBalance,
            'approved_amount' => $newBalance,
            'outstanding_balance' => $newBalance,
            'interest_rate' => $data['new_interest_rate'],
            'interest_method' => $originalLoan->interestMethod->value,
            'term' => $data['new_term'],
            'disbursement_date' => $data['restructure_date'],
            'status' => LoanStatusEnum::APPROVED->value,
            'approved_by' => $data['approved_by'],
            'approved_at' => date('Y-m-d H:i:s'),
            'finance_signatory' => $originalLoan->financeSignatory,
            'lender_signatory' => $originalLoan->lenderSignatory,
            'notes' => "Restructured from loan {$originalLoan->folio}",
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create payment configuration for new loan
        $paymentConfigId = $this->paymentConfigRepository->create([
            'loan_id' => $newLoanId,
            'payment_frequency' => $originalLoan->paymentConfiguration->paymentFrequency,
            'payment_day' => $data['payment_day'],
            'payment_month_day' => $data['payment_month_day'],
            'first_payment_date' => $data['first_payment_date'],
            'last_payment_date' => null, // Will be calculated
            'payment_amount' => 0, // Will be calculated
        ]);

        // Generate new amortization table
        $newRows = $this->amortizationCalculator->calculateGermanSimple(
            $newBalance,
            $data['new_interest_rate'],
            $data['new_term'],
            $data['payment_day'],
            $data['payment_month_day'],
            new \DateTime($data['first_payment_date'])
        );

        foreach ($newRows as $index => $rowData) {
            $this->amortizationRepository->create([
                'loan_id' => $newLoanId,
                'row_number' => $index + 1,
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

        // Update payment config with calculated values
        $lastRow = end($newRows);
        $this->paymentConfigRepository->update($paymentConfigId, [
            'last_payment_date' => $lastRow['payment_date'],
            'payment_amount' => $newRows[0]['total_payment'],
        ]);

        // Mark original loan as restructured
        $this->loanRepository->update($originalLoan->id, [
            'status' => LoanStatusEnum::RESTRUCTURED->value,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record restructuring
        $this->restructuringRepository->create([
            'original_loan_id' => $originalLoan->id,
            'new_loan_id' => $newLoanId,
            'restructure_date' => $data['restructure_date'],
            'original_balance' => $originalLoan->outstandingBalance,
            'new_balance' => $newBalance,
            'reason' => $data['reason'],
            'approved_by' => $data['approved_by'],
            'notes' => $data['notes'] ?? null,
        ]);

        // Log events
        $this->eventLogger->log(
            $originalLoan->id,
            'loan_restructured',
            $data['approved_by'],
            [
                'new_loan_id' => $newLoanId,
                'new_folio' => $newFolio,
                'original_balance' => $originalLoan->outstandingBalance,
                'accumulated_interest' => $accumulatedInterest,
                'new_balance' => $newBalance,
                'reason' => $data['reason'],
            ]
        );

        $this->eventLogger->log(
            $newLoanId,
            'loan_created_from_restructure',
            $data['approved_by'],
            [
                'original_loan_id' => $originalLoan->id,
                'original_folio' => $originalLoan->folio,
                'new_term' => $data['new_term'],
                'new_interest_rate' => $data['new_interest_rate'],
            ]
        );

        return [
            'new_loan_id' => $newLoanId,
            'new_folio' => $newFolio,
            'new_balance' => $newBalance,
        ];
    }
}
