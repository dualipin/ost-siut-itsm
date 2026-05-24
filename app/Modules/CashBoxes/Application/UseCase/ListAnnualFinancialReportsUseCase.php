<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;

final readonly class ListAnnualFinancialReportsUseCase
{
    public function __construct(
        private AnnualFinancialReportRepositoryInterface $repository,
    ) {
    }

    public function execute(): array
    {
        return $this->repository->findAll();
    }
}