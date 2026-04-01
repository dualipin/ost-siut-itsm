<?php

declare(strict_types=1);

namespace App\Modules\Loan\Application\UseCase;

use App\Modules\Loan\Domain\Repository\LoanRepositoryInterface;
use App\Modules\Loan\Domain\Repository\LegalDocRepositoryInterface;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\Enum\DocumentTypeEnum;
use App\Modules\Loan\Domain\Exception\LoanNotFoundException;
use App\Modules\Loan\Domain\Exception\InvalidLoanStatusException;
use App\Modules\Loan\Application\Service\LoanEventLogger;

/**
 * Use Case: Validate Signed Documents
 * 
 * Business Flow:
 * 1. Loan must be in 'aprobado' status
 * 2. Finance officer uploads signed documents (Pagaré, Anuencia)
 * 3. System validates all required documents are present
 * 4. Transitions loan to 'activo' status
 * 5. Logs validation event
 */
final readonly class ValidateSignedDocumentsUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private LegalDocRepositoryInterface $legalDocRepository,
        private LoanEventLogger $eventLogger
    ) {
    }

    /**
     * @param array{
     *   loan_id: int,
     *   validated_by: int,
     *   documents: array<array{
     *     type: string,
     *     file_path: string,
     *     file_name: string,
     *     file_size: int,
     *     notes?: string
     *   }>
     * } $data
     * @throws LoanNotFoundException
     * @throws InvalidLoanStatusException
     */
    public function execute(array $data): void
    {
        $loan = $this->loanRepository->findById($data['loan_id']);
        if (!$loan) {
            throw new LoanNotFoundException("Loan with ID {$data['loan_id']} not found");
        }

        // Only approved loans can have documents validated
        if ($loan->status !== LoanStatusEnum::APPROVED) {
            throw new InvalidLoanStatusException(
                "Loan must be in 'aprobado' status to validate documents. Current status: {$loan->status->value}"
            );
        }

        // Store uploaded documents
        foreach ($data['documents'] as $docData) {
            $this->legalDocRepository->create([
                'loan_id' => $loan->id,
                'document_type' => $docData['type'],
                'file_path' => $docData['file_path'],
                'file_name' => $docData['file_name'],
                'file_size' => $docData['file_size'],
                'uploaded_by' => $data['validated_by'],
                'uploaded_at' => date('Y-m-d H:i:s'),
                'notes' => $docData['notes'] ?? null,
            ]);
        }

        // Validate all required documents are present
        $requiredDocs = [
            DocumentTypeEnum::PAGARE->value,
            DocumentTypeEnum::ANUENCIA->value,
        ];

        $existingDocs = $this->legalDocRepository->findByLoanId($loan->id);
        $existingTypes = array_map(fn($doc) => $doc->documentType->value, $existingDocs);

        foreach ($requiredDocs as $requiredType) {
            if (!in_array($requiredType, $existingTypes, true)) {
                throw new InvalidLoanStatusException(
                    "Missing required document: {$requiredType}"
                );
            }
        }

        // Transition to 'activo' status
        $this->loanRepository->update($loan->id, [
            'status' => LoanStatusEnum::ACTIVE->value,
            'activated_at' => date('Y-m-d H:i:s'),
        ]);

        // Log event
        $this->eventLogger->log(
            $loan->id,
            'documents_validated',
            $data['validated_by'],
            [
                'document_count' => count($data['documents']),
                'document_types' => array_column($data['documents'], 'type'),
            ]
        );
    }
}
