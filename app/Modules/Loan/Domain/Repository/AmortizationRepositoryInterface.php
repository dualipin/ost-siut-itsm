<?php

namespace App\Modules\Loan\Domain\Repository;

use App\Modules\Loan\Domain\Entity\AmortizationRow;

interface AmortizationRepositoryInterface
{
    public function findById(int $amortizationId): ?AmortizationRow;

    public function findByLoanId(int $loanId, bool $activeOnly = true): array;

    public function findByLoanIdAndVersion(int $loanId, int $version): array;

    public function save(AmortizationRow $row): int;

    public function saveAll(array $rows): void;

    public function update(AmortizationRow $row): void;

    public function deactivateByLoanId(int $loanId): void;

    public function getLatestVersionByLoanId(int $loanId): int;
}
