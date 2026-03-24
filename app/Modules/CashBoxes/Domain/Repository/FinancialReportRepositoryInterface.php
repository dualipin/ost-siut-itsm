<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\FinancialReport;

interface FinancialReportRepositoryInterface
{
    public function save(FinancialReport $report): void;

    /**
     * @return FinancialReport[]
     */
    public function findRecent(?int $boxId = null, int $limit = 30): array;
}
