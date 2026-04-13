<?php

namespace App\Modules\Loan\Domain\Repository;

interface PaymentConfigRepositoryInterface
{
    public function findByLoanId(int $loanId): array;

    /**
     * Returns payment configs joined with cat_income_types (adds income_type_name field).
     */
    public function findByLoanIdWithIncomeType(int $loanId): array;

    public function save(array $config): int;

    public function update(int $configId, array $data): void;

    /**
     * Updates only the document validation fields for a single config.
     */
    public function updateDocumentStatus(int $configId, string $status, ?string $observations): void;

    public function deleteByLoanId(int $loanId): void;
}
