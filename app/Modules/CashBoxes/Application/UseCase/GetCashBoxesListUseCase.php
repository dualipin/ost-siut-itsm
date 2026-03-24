<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Shared\Context\UserProviderInterface;

final readonly class GetCashBoxesListUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository
    ) {
    }

    public function execute(): array
    {
        return [
            'boxes' => $this->cashBoxRepository->findAll()
        ];
    }
}
