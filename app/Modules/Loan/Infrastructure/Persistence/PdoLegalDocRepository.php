<?php

namespace App\Modules\Loan\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Loan\Domain\Entity\LegalDocument;
use App\Modules\Loan\Domain\Enum\DocumentTypeEnum;
use App\Modules\Loan\Domain\Repository\LegalDocRepositoryInterface;
use DateTimeImmutable;

final class PdoLegalDocRepository extends PdoBaseRepository implements LegalDocRepositoryInterface
{
    public function findById(int $legalDocId): ?LegalDocument
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_legal_documents WHERE legal_doc_id = :id");
        $stmt->execute(['id' => $legalDocId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByLoanId(int $loanId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_legal_documents WHERE loan_id = :loan_id");
        $stmt->execute(['loan_id' => $loanId]);
        
        return array_map([$this, 'hydrate'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findByLoanIdAndType(int $loanId, DocumentTypeEnum $type): ?LegalDocument
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM loan_legal_documents 
            WHERE loan_id = :loan_id AND document_type = :type
            ORDER BY version DESC LIMIT 1
        ");
        $stmt->execute(['loan_id' => $loanId, 'type' => $type->value]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function save(LegalDocument $document): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loan_legal_documents (
                loan_id, document_type, file_path, version, requires_user_signature,
                requires_finance_validation, generated_by
            ) VALUES (
                :loan_id, :document_type, :file_path, :version, :requires_user_signature,
                :requires_finance_validation, :generated_by
            )
        ");
        
        $stmt->execute([
            'loan_id' => $document->loanId(),
            'document_type' => $document->documentType()->value,
            'file_path' => $document->filePath(),
            'version' => $document->version(),
            'requires_user_signature' => $document->requiresUserSignature() ? 1 : 0,
            'requires_finance_validation' => $document->requiresFinanceValidation() ? 1 : 0,
            'generated_by' => $document->generatedBy()
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    public function update(LegalDocument $document): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE loan_legal_documents SET
                user_signature_url = :user_signature_url,
                user_signature_date = :user_signature_date,
                validated_by_finance = :validated_by_finance,
                validated_by = :validated_by,
                validation_date = :validation_date,
                validation_observations = :validation_observations
            WHERE legal_doc_id = :legal_doc_id
        ");
        
        $stmt->execute([
            'legal_doc_id' => $document->legalDocId(),
            'user_signature_url' => $document->userSignatureUrl(),
            'user_signature_date' => $document->userSignatureDate()?->format('Y-m-d H:i:s'),
            'validated_by_finance' => $document->isValidatedByFinance() ? 1 : 0,
            'validated_by' => $document->validatedBy(),
            'validation_date' => $document->validationDate()?->format('Y-m-d H:i:s'),
            'validation_observations' => $document->validationObservations()
        ]);
    }

    public function areAllRequiredDocsValidated(int $loanId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM loan_legal_documents 
            WHERE loan_id = :loan_id 
            AND requires_finance_validation = 1 
            AND validated_by_finance = 0
        ");
        $stmt->execute(['loan_id' => $loanId]);
        
        return (int) $stmt->fetchColumn() === 0;
    }

    private function hydrate(array $row): LegalDocument
    {
        return new LegalDocument(
            legalDocId: (int) $row['legal_doc_id'],
            loanId: (int) $row['loan_id'],
            documentType: DocumentTypeEnum::from($row['document_type']),
            filePath: $row['file_path'],
            version: (int) $row['version'],
            requiresUserSignature: (bool) $row['requires_user_signature'],
            userSignatureUrl: $row['user_signature_url'],
            userSignatureDate: $row['user_signature_date'] ? new DateTimeImmutable($row['user_signature_date']) : null,
            requiresFinanceValidation: (bool) $row['requires_finance_validation'],
            validatedByFinance: (bool) $row['validated_by_finance'],
            validatedBy: $row['validated_by'] ? (int) $row['validated_by'] : null,
            validationDate: $row['validation_date'] ? new DateTimeImmutable($row['validation_date']) : null,
            validationObservations: $row['validation_observations'],
            generationDate: new DateTimeImmutable($row['generation_date']),
            generatedBy: $row['generated_by'] ? (int) $row['generated_by'] : null
        );
    }
}
