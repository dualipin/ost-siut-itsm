<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\AnnualFinancialReport;

interface AnnualFinancialReportRepositoryInterface
{
    /**
     * @return AnnualFinancialReport[]
     */
    public function findAll(): array;

    public function findById(int $id): ?AnnualFinancialReport;

    public function findByYear(int $year): ?AnnualFinancialReport;

    public function save(AnnualFinancialReport $report): int;

    public function update(AnnualFinancialReport $report): void;

    public function delete(int $id): void;
}