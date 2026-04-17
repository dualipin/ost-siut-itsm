<?php

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\ElectronicSignatureService;
use App\Modules\Loan\Application\Service\FolioGenerator;
use App\Modules\Loan\Application\Service\LoanEventLogger;
use App\Modules\Loan\Application\Service\PdfGeneratorInterface;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Entity\LegalDocument;
use App\Modules\Loan\Domain\Enum\DocumentTypeEnum;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Repository\LegalDocRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\InterestRate;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final readonly class ReviewLoanApplicationUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private LegalDocRepositoryInterface $legalDocRepository,
        private FolioGenerator $folioGenerator,
        private ElectronicSignatureService $signatureService,
        private PdfGeneratorInterface $pdfGenerator,
        private LoanEventLogger $eventLogger,
        private AmortizationCalculator $amortizationCalculator,
        private PaymentConfigRepositoryInterface $paymentConfigRepository
    ) {}

    /**
     * Approve loan application
     */
    public function approve(
        int $loanId,
        int $reviewerId,
        Money $approvedAmount,
        InterestRate $appliedRate,
        float $dailyDefaultRate,
        int $termFortnights,
        string $financeSignatoryCurp,
        string $lenderSignatoryCurp,
        ?string $adminObservations = null
    ): void {
        $loan = $this->loanRepository->findById($loanId);
        
        if (!$loan) {
            throw LoanNotFoundException::withId($loanId);
        }
        
        if ($loan->status() !== LoanStatusEnum::Submitted) {
            throw InvalidLoanStatusException::cannotReview($loan->status());
        }

        // Generate folio
        $folio = $this->folioGenerator->generate();
        
        // Generate electronic signatures
        $now = new DateTimeImmutable();
        $financeSignature = $this->signatureService->generate($financeSignatoryCurp, $now);
        $lenderSignature = $this->signatureService->generate($lenderSignatoryCurp, $now);

        $paymentConfigs = $this->paymentConfigRepository->findByLoanIdWithIncomeType($loanId);
        
        // Calculate estimated total
        $amortization = $paymentConfigs !== []
            ? $this->amortizationCalculator->calculateByPaymentConfigurations(
                $approvedAmount,
                $appliedRate,
                $now,
                $paymentConfigs
            )
            : $this->amortizationCalculator->calculateGermanSimple(
                $approvedAmount,
                $appliedRate,
                $termFortnights,
                $now
            );

        if ($amortization !== []) {
            $termFortnights = count($amortization);
        }
        
        $estimatedTotal = array_reduce(
            $amortization,
            fn($sum, $row) => $sum->add($row->totalScheduledPayment()),
            Money::zero()
        );
        
        $firstPaymentDate = $amortization !== [] ? $amortization[0]->scheduledDate() : null;
        $lastPaymentDate = $amortization !== [] ? end($amortization)->scheduledDate() : null;
        
        // Update loan
        $approvedLoan = new Loan(
            loanId: $loan->loanId(),
            userId: $loan->userId(),
            folio: $folio,
            requestedAmount: $loan->requestedAmount(),
            approvedAmount: $approvedAmount,
            appliedInterestRate: $appliedRate,
            dailyDefaultRate: $dailyDefaultRate,
            estimatedTotalToPay: $estimatedTotal,
            outstandingBalance: $approvedAmount,
            termMonths: null,
            termFortnights: $termFortnights,
            firstPaymentDate: $firstPaymentDate,
            lastScheduledPaymentDate: $lastPaymentDate,
            applicationDate: $loan->applicationDate(),
            documentReviewDate: $now,
            approvalDate: $now,
            documentGenerationDate: $now,
            signatureValidationDate: null,
            disbursementDate: null,
            totalLiquidationDate: null,
            status: LoanStatusEnum::Approved,
            originalLoanId: $loan->originalLoanId(),
            rejectionReason: null,
            adminObservations: $adminObservations,
            internalObservations: $loan->internalObservations(),
            financeSignatory: $financeSignature,
            lenderSignatory: $lenderSignature,
            requiresRestructuring: false,
            createdBy: $loan->createdBy(),
            deletionDate: null
        );
        
        $this->loanRepository->update($approvedLoan);
        
        $loanDetail = $this->loanRepository->findDetailById($loanId) ?? [];

        $userData = [
            'user_id' => $loan->userId(),
            'name' => trim((string) ($loanDetail['borrower_name'] ?? 'Usuario')),
            'curp' => (string) ($loanDetail['borrower_curp'] ?? $lenderSignatoryCurp),
            'category' => (string) ($loanDetail['borrower_category'] ?? ''),
            'phone' => (string) ($loanDetail['borrower_phone'] ?? ''),
            'department' => (string) ($loanDetail['borrower_department'] ?? ''),
            'bank_name' => (string) ($loanDetail['borrower_bank_name'] ?? ''),
            'interbank_code' => (string) ($loanDetail['borrower_interbank_code'] ?? ''),
            'bank_account' => (string) ($loanDetail['borrower_bank_account'] ?? ''),
        ];

        // Generate legal documents
        $this->generateLegalDocument(
            $loanId,
            DocumentTypeEnum::PromissoryNote,
            $this->pdfGenerator->generatePromissoryNote($approvedLoan, $userData),
            $reviewerId,
            true,
            true
        );
        
        $this->generateLegalDocument(
            $loanId,
            DocumentTypeEnum::ConsentForm,
            $this->pdfGenerator->generateConsentForm($approvedLoan, $userData),
            $reviewerId,
            true,
            true
        );
        
        $this->generateLegalDocument(
            $loanId,
            DocumentTypeEnum::ApplicationForm,
            $this->pdfGenerator->generateApplicationForm($approvedLoan, $userData, $paymentConfigs),
            $reviewerId,
            true,
            true
        );
        
        $this->eventLogger->logLoanApproved($loanId, $reviewerId, $approvedAmount->amount());
        $this->eventLogger->logDocumentsGenerated($loanId);
    }

    /**
     * Reject loan application
     */
    public function reject(int $loanId, int $reviewerId, string $rejectionReason): void
    {
        $loan = $this->loanRepository->findById($loanId);
        
        if (!$loan) {
            throw LoanNotFoundException::withId($loanId);
        }
        
        if ($loan->status() !== LoanStatusEnum::Submitted) {
            throw InvalidLoanStatusException::cannotReview($loan->status());
        }
        
        $rejectedLoan = new Loan(
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
            documentReviewDate: new DateTimeImmutable(),
            approvalDate: null,
            documentGenerationDate: null,
            signatureValidationDate: null,
            disbursementDate: null,
            totalLiquidationDate: null,
            status: LoanStatusEnum::Rejected,
            originalLoanId: $loan->originalLoanId(),
            rejectionReason: $rejectionReason,
            adminObservations: $loan->adminObservations(),
            internalObservations: $loan->internalObservations(),
            financeSignatory: $loan->financeSignatory(),
            lenderSignatory: $loan->lenderSignatory(),
            requiresRestructuring: false,
            createdBy: $loan->createdBy(),
            deletionDate: null
        );
        
        $this->loanRepository->update($rejectedLoan);
        $this->eventLogger->logLoanRejected($loanId, $reviewerId, $rejectionReason);
    }

    /**
     * Put loan on hold
     */
    public function putOnHold(int $loanId, int $reviewerId, ?string $reason = null): void
    {
        $loan = $this->loanRepository->findById($loanId);
        
        if (!$loan) {
            throw LoanNotFoundException::withId($loanId);
        }
        
        if ($loan->status() !== LoanStatusEnum::Submitted) {
            throw InvalidLoanStatusException::cannotReview($loan->status());
        }
        
        $onHoldLoan = new Loan(
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
            documentReviewDate: new DateTimeImmutable(),
            approvalDate: null,
            documentGenerationDate: null,
            signatureValidationDate: null,
            disbursementDate: null,
            totalLiquidationDate: null,
            status: LoanStatusEnum::OnHold,
            originalLoanId: $loan->originalLoanId(),
            rejectionReason: null,
            adminObservations: $reason,
            internalObservations: $loan->internalObservations(),
            financeSignatory: $loan->financeSignatory(),
            lenderSignatory: $loan->lenderSignatory(),
            requiresRestructuring: false,
            createdBy: $loan->createdBy(),
            deletionDate: null
        );
        
        $this->loanRepository->update($onHoldLoan);
    }

    private function generateLegalDocument(
        int $loanId,
        DocumentTypeEnum $type,
        string $pdfPath,
        int $generatedBy,
        bool $requiresUserSignature,
        bool $requiresFinanceValidation
    ): void {
        $document = new LegalDocument(
            legalDocId: null,
            loanId: $loanId,
            documentType: $type,
            filePath: $pdfPath,
            version: 1,
            requiresUserSignature: $requiresUserSignature,
            userSignatureUrl: null,
            userSignatureDate: null,
            requiresFinanceValidation: $requiresFinanceValidation,
            validatedByFinance: false,
            validatedBy: null,
            validationDate: null,
            validationObservations: null,
            generationDate: new DateTimeImmutable(),
            generatedBy: $generatedBy
        );
        
        $this->legalDocRepository->save($document);
    }
}
