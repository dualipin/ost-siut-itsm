<?php

namespace App\Modules\Loan\Domain\Repository;

use App\Modules\Loan\Domain\Entity\Loan;
use App\Modules\Loan\Domain\Enum\LoanStatusEnum;
use App\Modules\Loan\Domain\ValueObject\Folio;

interface LoanRepositoryInterface
{
    public function findById(int $loanId): ?Loan;

    public function findByFolio(Folio $folio): ?Loan;

    public function findByUserId(int $userId): array;

    public function findByStatus(LoanStatusEnum $status): array;

    public function save(Loan $loan): int;

    public function update(Loan $loan): void;

    public function getLastFolioForYear(int $year): ?Folio;

    public function countByUserAndStatus(int $userId, LoanStatusEnum $status): int;

    /**
     * Returns a flat array with all loan fields + borrower user data
     * (name, surnames, email, curp, department, category, salary)
     * plus computed fields: days_elapsed.
     * Returns null if not found.
     */
    public function findDetailById(int $loanId): ?array;
}
