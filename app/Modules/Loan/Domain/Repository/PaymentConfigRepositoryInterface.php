<?php

namespace App\Modules\Loan\Domain\Repository;

interface PaymentConfigRepositoryInterface
{
    public function findByLoanId(int $loanId): array;

    public function save(array $config): int;

    public function update(int $configId, array $data): void;

    public function deleteByLoanId(int $loanId): void;
}
