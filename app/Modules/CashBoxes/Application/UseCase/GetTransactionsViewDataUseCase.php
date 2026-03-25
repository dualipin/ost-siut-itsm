<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;

final readonly class GetTransactionsViewDataUseCase
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private CashBoxRepositoryInterface $cashBoxRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(?int $boxId = null, ?string $type = null, ?int $categoryId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $boxes = $this->cashBoxRepository->findAll();
        $categories = $this->categoryRepository->findAll();
        $contributors = $this->userRepository->listado(true);

        $selectedBoxId = $boxId ?: ($boxes[0]->boxId ?? null);

        $transactions = $this->transactionRepository->findByCriteria(
            $selectedBoxId,
            $type,
            $categoryId,
            $startDate,
            $endDate
        );

        return [
            'boxes' => $boxes,
            'categories' => $categories,
            'contributors' => $contributors,
            'transactions' => $transactions,
            'selectedBoxId' => $selectedBoxId,
            'filters' => [
                'type' => $type,
                'categoryId' => $categoryId,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
        ];
    }
}
