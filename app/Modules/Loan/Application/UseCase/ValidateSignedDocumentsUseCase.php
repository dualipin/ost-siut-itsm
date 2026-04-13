<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Application\Service\AmortizationCalculator;
use App\Modules\Loan\Application\Service\LoanEventLogger;
use App\Modules\Loan\Domain\Entity\AmortizationRow;
use App\Modules\Loan\Domain\Entity\LegalDocument;
use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LegalDocRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
use App\Modules\Loan\Domain\ValueObject\Money;
use DateTimeImmutable;

final readonly class ValidateSignedDocumentsUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private LegalDocRepositoryInterface $legalDocRepository,
        private PaymentConfigRepositoryInterface $paymentConfigRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private AmortizationCalculator $amortizationCalculator,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * Register a newly uploaded signed document from the borrower.
     */
    public function uploadSignedDocument(int $loanId, int $legalDocId, string $signedFilePath): void
    {
        $this->requireLoanInApprovalFlow($loanId);
        $document = $this->requireLoanDocument($loanId, $legalDocId);

        // A new upload invalidates previous finance review, requiring re-validation.
        $updatedDocument = $this->rebuildDocument(
            document: $document,
            userSignatureUrl: $signedFilePath,
            userSignatureDate: new DateTimeImmutable(),
            validatedByFinance: false,
            validatedBy: null,
            validationDate: null,
            validationObservations: null,
        );

        $this->legalDocRepository->update($updatedDocument);
    }

    /**
     * Review one legal document. When all required docs are signed and validated,
     * loan transitions to active and final amortization schedule is generated.
     *
     * @return array{loan_activated: bool, message: string}
     */
    public function reviewSignedDocument(
        int $loanId,
        int $legalDocId,
        int $reviewerId,
        string $validationStatus,
        ?string $observations = null
    ): array {
        $loan = $this->requireLoanInApprovalFlow($loanId);
        $document = $this->requireLoanDocument($loanId, $legalDocId);

        if (!$document->isSignedByUser()) {
            throw new InvalidLoanStatusException('El documento debe estar firmado por el prestador antes de validarlo.');
        }

        if (!in_array($validationStatus, ['validado', 'rechazado'], true)) {
            throw new InvalidLoanStatusException('Estado de validacion no permitido para documentos legales.');
        }

        if ($validationStatus === 'rechazado' && trim((string) $observations) === '') {
            throw new InvalidLoanStatusException('Debes indicar observaciones para rechazar un documento.');
        }

        $isValidated = $validationStatus === 'validado';
        $updatedDocument = $this->rebuildDocument(
            document: $document,
            userSignatureUrl: $document->userSignatureUrl(),
            userSignatureDate: $document->userSignatureDate(),
            validatedByFinance: $isValidated,
            validatedBy: $reviewerId,
            validationDate: new DateTimeImmutable(),
            validationObservations: $observations,
        );

        $this->legalDocRepository->update($updatedDocument);

        if (!$isValidated) {
            return [
                'loan_activated' => false,
                'message' => 'Documento rechazado. El prestador puede corregir y volver a subirlo.',
            ];
        }

        if (!$this->canActivateLoan($loanId)) {
            return [
                'loan_activated' => false,
                'message' => 'Documento validado. Aun faltan documentos por firmar o validar.',
            ];
        }

        $this->activateLoan($loan, $reviewerId);

        return [
            'loan_activated' => true,
            'message' => 'Todos los documentos fueron validados. El prestamo quedo activo con corrida financiera final.',
        ];
    }

    private function requireLoanInApprovalFlow(int $loanId): Loan
    {
        $loan = $this->loanRepository->findById($loanId);
        if ($loan === null) {
            throw LoanNotFoundException::withId($loanId);
        }

        if ($loan->status() !== LoanStatusEnum::Approved) {
            throw InvalidLoanStatusException::cannotValidate($loan->status());
        }

        return $loan;
    }

    private function requireLoanDocument(int $loanId, int $legalDocId): LegalDocument
    {
        $document = $this->legalDocRepository->findById($legalDocId);
        if ($document === null || $document->loanId() !== $loanId) {
            throw new LoanNotFoundException('Documento legal no encontrado para el prestamo indicado.');
        }

        return $document;
    }

    private function canActivateLoan(int $loanId): bool
    {
        $documents = $this->legalDocRepository->findByLoanId($loanId);
        if ($documents === []) {
            return false;
        }

        foreach ($documents as $document) {
            if ($document->requiresUserSignature() && !$document->isSignedByUser()) {
                return false;
            }

            if ($document->requiresFinanceValidation() && !$document->isValidatedByFinance()) {
                return false;
            }
        }

        return true;
    }

    private function activateLoan(Loan $loan, int $validatedBy): void
    {
        $loanId = (int) $loan->loanId();
        $validationDate = new DateTimeImmutable();
        $principalAmount = $loan->approvedAmount() ?? $loan->requestedAmount();
        $paymentConfigurations = $this->paymentConfigRepository->findByLoanIdWithIncomeType($loanId);

        $generatedRows = $this->amortizationCalculator->calculateByPaymentConfigurations(
            $principalAmount,
            $loan->appliedInterestRate(),
            $validationDate,
            $paymentConfigurations
        );

        $rowsForLoan = $this->assignRowsToLoan($loanId, $generatedRows);
        $estimatedTotal = $loan->estimatedTotalToPay();
        $firstPaymentDate = $loan->firstPaymentDate();
        $lastPaymentDate = $loan->lastScheduledPaymentDate();

        if ($rowsForLoan !== []) {
            $this->amortizationRepository->deactivateByLoanId($loanId);
            $this->amortizationRepository->saveAll($rowsForLoan);

            $firstPaymentDate = $rowsForLoan[0]->scheduledDate();
            $lastRow = end($rowsForLoan);
            $lastPaymentDate = $lastRow instanceof AmortizationRow ? $lastRow->scheduledDate() : $lastPaymentDate;

            $estimatedTotalAmount = 0.0;
            foreach ($rowsForLoan as $row) {
                $estimatedTotalAmount += $row->totalScheduledPayment()->amount();
            }
            $estimatedTotal = Money::fromFloat($estimatedTotalAmount);
        }

        $activeLoan = new Loan(
            loanId: $loan->loanId(),
            userId: $loan->userId(),
            folio: $loan->folio(),
            requestedAmount: $loan->requestedAmount(),
            approvedAmount: $loan->approvedAmount(),
            appliedInterestRate: $loan->appliedInterestRate(),
            dailyDefaultRate: $loan->dailyDefaultRate(),
            estimatedTotalToPay: $estimatedTotal,
            outstandingBalance: $principalAmount,
            termMonths: $loan->termMonths(),
            termFortnights: $loan->termFortnights(),
            firstPaymentDate: $firstPaymentDate,
            lastScheduledPaymentDate: $lastPaymentDate,
            applicationDate: $loan->applicationDate(),
            documentReviewDate: $loan->documentReviewDate(),
            approvalDate: $loan->approvalDate(),
            documentGenerationDate: $loan->documentGenerationDate(),
            signatureValidationDate: $validationDate,
            disbursementDate: $validationDate,
            totalLiquidationDate: $loan->totalLiquidationDate(),
            status: LoanStatusEnum::Active,
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

        $this->loanRepository->update($activeLoan);
        $this->eventLogger->logDocumentsValidated($loanId, $validatedBy);
        $this->eventLogger->logLoanActivated($loanId);
    }

    /**
     * @param AmortizationRow[] $rows
     * @return AmortizationRow[]
     */
    private function assignRowsToLoan(int $loanId, array $rows): array
    {
        $mappedRows = [];
        $paymentNumber = 1;

        foreach ($rows as $row) {
            $mappedRows[] = new AmortizationRow(
                amortizationId: null,
                loanId: $loanId,
                paymentNumber: $paymentNumber,
                incomeTypeId: $row->incomeTypeId(),
                scheduledDate: $row->scheduledDate(),
                initialBalance: $row->initialBalance(),
                principal: $row->principal(),
                ordinaryInterest: $row->ordinaryInterest(),
                totalScheduledPayment: $row->totalScheduledPayment(),
                finalBalance: $row->finalBalance(),
                paymentStatus: $row->paymentStatus(),
                actualPaymentDate: null,
                actualPaidAmount: Money::zero(),
                daysOverdue: 0,
                generatedDefaultInterest: Money::zero(),
                paidBy: null,
                paymentReceipt: null,
                tableVersion: 1,
                active: true
            );

            $paymentNumber++;
        }

        return $mappedRows;
    }

    private function rebuildDocument(
        LegalDocument $document,
        ?string $userSignatureUrl,
        ?DateTimeImmutable $userSignatureDate,
        bool $validatedByFinance,
        ?int $validatedBy,
        ?DateTimeImmutable $validationDate,
        ?string $validationObservations,
    ): LegalDocument {
        return new LegalDocument(
            legalDocId: $document->legalDocId(),
            loanId: $document->loanId(),
            documentType: $document->documentType(),
            filePath: $document->filePath(),
            version: $document->version(),
            requiresUserSignature: $document->requiresUserSignature(),
            userSignatureUrl: $userSignatureUrl,
            userSignatureDate: $userSignatureDate,
            requiresFinanceValidation: $document->requiresFinanceValidation(),
            validatedByFinance: $validatedByFinance,
            validatedBy: $validatedBy,
            validationDate: $validationDate,
            validationObservations: $validationObservations,
            generationDate: $document->generationDate(),
            generatedBy: $document->generatedBy(),
        );
    }
}
