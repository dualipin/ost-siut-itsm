<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\AmortizationRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LegalDocRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LoanRestructuringRepositoryInterface;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;

/**
 * Read-only Use Case that aggregates all data needed for the loan detail/review page.
 * Performs no write operations.
 */
final readonly class GetLoanDetailUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private PaymentConfigRepositoryInterface $paymentConfigRepository,
        private AmortizationRepositoryInterface $amortizationRepository,
        private LegalDocRepositoryInterface $legalDocRepository,
        private LoanRestructuringRepositoryInterface $restructuringRepository,
    ) {}

    /**
     * Returns a complete aggregate array for the loan detail view, or null if not found.
     *
     * @return array{
     *   loan: array,
     *   payment_configs: array,
     *   amortization: array,
     *   legal_docs: array,
     *   restructuring: object|null,
     *   original_loan: array|null
     * }|null
     */
    public function execute(int $loanId): ?array
    {
        // 1. Core loan data + borrower info (single optimised JOIN query)
        $loan = $this->loanRepository->findDetailById($loanId);

        if ($loan === null) {
            return null;
        }

        // 2. Payment configurations with income type labels
        $paymentConfigs = $this->paymentConfigRepository->findByLoanIdWithIncomeType($loanId);

        // 3. Amortization schedule (active rows only)
        $amortizationEntities = $this->amortizationRepository->findByLoanId($loanId, activeOnly: true);
        $amortization = array_map(
            static fn($row) => [
                'amortization_id'        => $row->amortizationId(),
                'payment_number'         => $row->paymentNumber(),
                'income_type_id'         => $row->incomeTypeId(),
                'scheduled_date'         => $row->scheduledDate()->format('Y-m-d'),
                'initial_balance'        => $row->initialBalance()->amount(),
                'principal'              => $row->principal()->amount(),
                'ordinary_interest'      => $row->ordinaryInterest()->amount(),
                'total_scheduled_payment' => $row->totalScheduledPayment()->amount(),
                'final_balance'          => $row->finalBalance()->amount(),
                'payment_status'         => $row->paymentStatus()->value,
                'table_version'          => $row->tableVersion(),
            ],
            $amortizationEntities
        );

        // 4. Legal documents
        $legalDocEntities = $this->legalDocRepository->findByLoanId($loanId);
        $legalDocs = array_map(
            static fn($doc) => [
                'legal_doc_id'               => $doc->legalDocId(),
                'document_type'              => $doc->documentType()->value,
                'file_path'                  => $doc->filePath(),
                'version'                    => $doc->version(),
                'requires_user_signature'    => $doc->requiresUserSignature(),
                'user_signature_url'         => $doc->userSignatureUrl(),
                'user_signature_date'        => $doc->userSignatureDate()?->format('d/m/Y H:i'),
                'requires_finance_validation' => $doc->requiresFinanceValidation(),
                'validated_by_finance'       => $doc->isValidatedByFinance(),
                'validated_by'               => $doc->validatedBy(),
                'validation_date'            => $doc->validationDate()?->format('d/m/Y H:i'),
                'validation_observations'    => $doc->validationObservations(),
                'generation_date'            => $doc->generationDate()->format('d/m/Y H:i'),
            ],
            $legalDocEntities
        );

        // 5. Restructuring info — only if this loan originated from another
        $restructuring = null;
        $originalLoan  = null;

        if (!empty($loan['original_loan_id'])) {
            $restructuringResult = $this->restructuringRepository->findByNewLoanId($loanId);
            if ($restructuringResult !== null) {
                $restructuring = $restructuringResult;
            }
            $originalLoan = $this->loanRepository->findDetailById((int) $loan['original_loan_id']);
        }

        return [
            'loan'            => $loan,
            'payment_configs' => $paymentConfigs,
            'amortization'    => $amortization,
            'legal_docs'      => $legalDocs,
            'restructuring'   => $restructuring,
            'original_loan'   => $originalLoan,
        ];
    }
}
