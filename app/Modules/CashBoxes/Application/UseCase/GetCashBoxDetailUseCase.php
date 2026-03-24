<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;

final readonly class GetCashBoxDetailUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository,
        private TransactionRepositoryInterface $transactionRepository
    ) {
    }

    /**
     * @throws CashBoxNotFoundException
     */
    public function execute(int $boxId): array
    {
        $box = $this->cashBoxRepository->findById($boxId);
        $transactions = $this->transactionRepository->findByBoxId($boxId);

        return [
            'box' => $box,
            'recentTransactions' => array_slice($transactions, 0, 10)
        ];
    }
}
