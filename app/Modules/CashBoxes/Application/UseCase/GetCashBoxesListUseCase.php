<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;

final readonly class GetCashBoxesListUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository
    ) {
    }

    public function execute(?string $name = null, ?string $status = null, ?float $minInitialBalance = null, ?float $maxInitialBalance = null, ?float $minCurrentBalance = null, ?float $maxCurrentBalance = null, string $sortBy = 'created_at', string $sortOrder = 'DESC'): array
    {
        $statusEnum = match($status) {
            'open' => BoxStatusEnum::OPEN,
            'closed' => BoxStatusEnum::CLOSED,
            default => null,
        };

        return [
            'boxes' => $this->cashBoxRepository->findFiltered($name, $statusEnum, $minInitialBalance, $maxInitialBalance, $minCurrentBalance, $maxCurrentBalance, $sortBy, $sortOrder),
            'filters' => [
                'name' => $name,
                'status' => $status,
                'min_initial' => $minInitialBalance,
                'max_initial' => $maxInitialBalance,
                'min_current' => $minCurrentBalance,
                'max_current' => $maxCurrentBalance,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ];
    }
}
