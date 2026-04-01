<?php

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\LoanEventLogger;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
use App\Modules\Loan\Domain\Service\InterestRateProvider;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Modules\Loan\Domain\ValueObject\Money;
use App\Shared\Domain\Enum\RoleEnum;
use DateTimeImmutable;

final readonly class SubmitLoanApplicationUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private PaymentConfigRepositoryInterface $paymentConfigRepository,
        private InterestRateProvider $interestRateProvider,
        private AmortizationCalculator $amortizationCalculator,
        private LoanEventLogger $eventLogger
    ) {}

    public function execute(
        int $userId,
        RoleEnum $userRole,
        Money $requestedAmount,
        array $paymentConfigs, // [['income_type_id' => 1, 'fortnights' => 11, 'interest_method' => 'simple_aleman', 'document_path' => '...']]
        ?InterestRate $customRate = null
    ): array {
        // Get standard interest rate or use custom
        $interestRate = $customRate ?? $this->interestRateProvider->getStandardRate($userId, $userRole);
        
        // Calculate total fortnights
        $totalFortnights = array_sum(array_column($paymentConfigs, 'fortnights'));
        
        // Validate deadline (must end before Dec 31 of current year)
        $startDate = new DateTimeImmutable();
        $lastPaymentDate = $this->amortizationCalculator->calculateLastPaymentDate($startDate, $totalFortnights);
        $yearEnd = new DateTimeImmutable($startDate->format('Y') . '-12-31');
        
        if ($lastPaymentDate > $yearEnd) {
            throw new \InvalidArgumentException("El plazo excede el 31 de diciembre del año en curso");
        }
        
        // Create loan
        $loan = new Loan(
            loanId: null,
            userId: $userId,
            folio: null,
            requestedAmount: $requestedAmount,
            approvedAmount: null,
            appliedInterestRate: $interestRate,
            dailyDefaultRate: null,
            estimatedTotalToPay: null,
            outstandingBalance: Money::zero(),
            termMonths: null,
            termFortnights: $totalFortnights,
            firstPaymentDate: null,
            lastScheduledPaymentDate: null,
            applicationDate: new DateTimeImmutable(),
            documentReviewDate: null,
            approvalDate: null,
            documentGenerationDate: null,
            signatureValidationDate: null,
            disbursementDate: null,
            totalLiquidationDate: null,
            status: LoanStatusEnum::Draft, // Draft by default
            originalLoanId: null,
            rejectionReason: null,
            adminObservations: null,
            internalObservations: null,
            financeSignatory: null,
            lenderSignatory: null,
            requiresRestructuring: false,
            createdBy: $userId,
            deletionDate: null
        );
        
        $loanId = $this->loanRepository->save($loan);
        
        // Save payment configurations
        foreach ($paymentConfigs as $config) {
            $this->paymentConfigRepository->save([
                'loan_id' => $loanId,
                'income_type_id' => $config['income_type_id'],
                'total_amount_to_deduct' => $requestedAmount->amount(),
                'number_of_installments' => $config['fortnights'],
                'amount_per_installment' => $requestedAmount->amount() / $config['fortnights'],
                'interest_method' => $config['interest_method'] ?? 'simple_aleman',
                'supporting_document_path' => $config['document_path'] ?? null,
                'document_status' => 'pendiente'
            ]);
        }
        
        // Generate simulation
        $amortization = $this->amortizationCalculator->calculateGermanSimple(
            $requestedAmount,
            $interestRate,
            $totalFortnights,
            $startDate
        );
        
        $this->eventLogger->logLoanCreated($loanId, $userId);
        
        return [
            'loan_id' => $loanId,
            'interest_rate' => $interestRate,
            'total_fortnights' => $totalFortnights,
            'last_payment_date' => $lastPaymentDate,
            'amortization_schedule' => $amortization
        ];
    }

    /**
     * Submit draft loan for review
     */
    public function submit(int $loanId): void
    {
        $loan = $this->loanRepository->findById($loanId);
        if (!$loan || !$loan->isDraft()) {
            throw new \InvalidArgumentException("Only draft loans can be submitted");
        }
        
        // Update status to submitted
        $submittedLoan = new Loan(
            loanId: $loan->loanId(),
            userId: $loan->userId(),
            folio: $loan->folio(),
            requestedAmount: $loan->requestedAmount(),
            approvedAmount: $loan->approvedAmount(),
            appliedInterestRate: $loan->appliedInterestRate(),
            dailyDefaultRate: $loan->dailyDefaultRate(),
            estimatedTotalToPay: $loan->estimatedTotalToPay(),
            outstandingBalance: $loan->outstandingBalance(),
            termMonths: $loan->termMonths(),
            termFortnights: $loan->termFortnights(),
            firstPaymentDate: $loan->firstPaymentDate(),
            lastScheduledPaymentDate: $loan->lastScheduledPaymentDate(),
            applicationDate: $loan->applicationDate(),
            documentReviewDate: $loan->documentReviewDate(),
            approvalDate: $loan->approvalDate(),
            documentGenerationDate: $loan->documentGenerationDate(),
            signatureValidationDate: $loan->signatureValidationDate(),
            disbursementDate: $loan->disbursementDate(),
            totalLiquidationDate: $loan->totalLiquidationDate(),
            status: LoanStatusEnum::Submitted,
            originalLoanId: $loan->originalLoanId(),
            rejectionReason: $loan->rejectionReason(),
            adminObservations: $loan->adminObservations(),
            internalObservations: $loan->internalObservations(),
            financeSignatory: $loan->financeSignatory(),
            lenderSignatory: $loan->lenderSignatory(),
            requiresRestructuring: $loan->requiresRestructuring(),
            createdBy: $loan->createdBy(),
            deletionDate: $loan->deletionDate()
        );
        
        $this->loanRepository->update($submittedLoan);
        $this->eventLogger->logLoanSubmitted($loanId);
    }
}
