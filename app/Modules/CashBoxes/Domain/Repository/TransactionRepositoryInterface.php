<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Repository;

use App\Modules\CashBoxes\Domain\Entity\BoxTransaction;
use App\Modules\CashBoxes\Domain\Entity\BoxTransfer;

interface TransactionRepositoryInterface
{
    public function saveTransaction(BoxTransaction $transaction): void;
    
    public function saveTransfer(BoxTransfer $transfer): void;

    /**
     * @return BoxTransaction[]
     */
    public function findByBoxId(int $boxId): array;
    
    public function nextTransactionId(): int;
    
    public function nextTransferId(): int;

    /**
     * @return BoxTransaction[]
     */
    public function findByCriteria(?int $boxId = null, ?string $type = null, ?int $categoryId = null, ?string $startDate = null, ?string $endDate = null): array;

    /**
     * @return array<int, array{month: string, total_income: float, total_expense: float}>
     */
    public function summarizeByPeriod(string $startDate, string $endDate, ?int $boxId = null): array;

    /**
     * @return array<int, array{contribution_category: string, category_name: string, total_amount: float}>
     */
    public function totalsByContributionCategory(string $startDate, string $endDate, ?int $boxId = null): array;
}
