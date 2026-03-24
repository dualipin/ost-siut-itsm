<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;

final readonly class GetTransactionsViewDataUseCase
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private CashBoxRepositoryInterface $cashBoxRepository,
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(?int $boxId = null): array
    {
        $boxes = $this->cashBoxRepository->findAll();
        $categories = $this->categoryRepository->findAll();
        
        $transactions = [];
        if ($boxId) {
            $transactions = $this->transactionRepository->findByBoxId($boxId);
        } else {
            // If no boxId, maybe get all transactions? 
            // For now, let's just assume we want from the first box or handle "all" if needed.
            // Let's just return empty or the first one for simplicity.
            if (count($boxes) > 0) {
                $transactions = $this->transactionRepository->findByBoxId($boxes[0]->boxId);
            }
        }

        return [
            'boxes' => $boxes,
            'categories' => $categories,
            'transactions' => $transactions,
            'selectedBoxId' => $boxId ?: ($boxes[0]->boxId ?? null)
        ];
    }
}
