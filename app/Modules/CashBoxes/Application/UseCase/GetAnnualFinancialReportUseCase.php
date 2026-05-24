<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\AnnualFinancialReport;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;
use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;

final readonly class GetAnnualFinancialReportUseCase
{
    public function __construct(
        private AnnualFinancialReportRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): AnnualFinancialReport
    {
        $report = $this->repository->findById($id);

        if ($report === null) {
            throw new AnnualFinancialReportNotFoundException($id);
        }

        return $report;
    }
}