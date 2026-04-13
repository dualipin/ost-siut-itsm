<?php

namespace App\Modules\Loan\Domain\Repository;

use App\Modules\Loan\Domain\Entity\LegalDocument;
use App\Modules\Loan\Domain\Enum\DocumentTypeEnum;

interface LegalDocRepositoryInterface
{
    public function findById(int $legalDocId): ?LegalDocument;

    public function findByLoanId(int $loanId): array;

    public function findByLoanIdAndType(int $loanId, DocumentTypeEnum $type): ?LegalDocument;

    public function save(LegalDocument $document): int;

    public function update(LegalDocument $document): void;

    public function areAllRequiredDocsValidated(int $loanId): bool;
}
