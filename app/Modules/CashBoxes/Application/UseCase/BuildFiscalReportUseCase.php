<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;

final readonly class BuildFiscalReportUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository,
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /**
     * @return array{
     *   box: object|null,
     *   periodStart: string,
     *   periodEnd: string,
     *   monthly: array<int, array{month: string, total_income: float, total_expense: float}>,
     *   contributionTotals: array<int, array{contribution_category: string, category_name: string, total_amount: float}>,
     *   summary: array{totalIncome: float, totalExpense: float, netBalance: float}
     * }
     */
    public function execute(string $periodStart, string $periodEnd, ?int $boxId = null): array
    {
        $box = null;
        if ($boxId !== null) {
            $box = $this->cashBoxRepository->findById($boxId);
        }

        $monthly = $this->transactionRepository->summarizeByPeriod($periodStart, $periodEnd, $boxId);
        $contributionTotals = $this->transactionRepository->totalsByContributionCategory($periodStart, $periodEnd, $boxId);

        $totalIncome = 0.0;
        $totalExpense = 0.0;
        foreach ($monthly as $row) {
            $totalIncome += $row['total_income'];
            $totalExpense += $row['total_expense'];
        }

        return [
            'box' => $box,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'monthly' => $monthly,
            'contributionTotals' => $contributionTotals,
            'summary' => [
                'totalIncome' => $totalIncome,
                'totalExpense' => $totalExpense,
                'netBalance' => $totalIncome - $totalExpense,
            ],
        ];
    }
}
