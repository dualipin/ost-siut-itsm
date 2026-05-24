<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;
use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;

final readonly class DeleteAnnualFinancialReportUseCase
{
    public function __construct(
        private AnnualFinancialReportRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new AnnualFinancialReportNotFoundException($id);
        }

        $this->repository->delete($id);
    }
}