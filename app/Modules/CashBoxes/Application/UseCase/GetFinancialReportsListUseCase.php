<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\FinancialReportRepositoryInterface;

final readonly class GetFinancialReportsListUseCase
{
    public function __construct(
        private FinancialReportRepositoryInterface $financialReportRepository,
        private CashBoxRepositoryInterface $cashBoxRepository,
    ) {
    }

    public function execute(?int $boxId = null): array
    {
        return [
            'boxes' => $this->cashBoxRepository->findAll(),
            'reports' => $this->financialReportRepository->findRecent($boxId),
            'selectedBoxId' => $boxId,
        ];
    }
}
