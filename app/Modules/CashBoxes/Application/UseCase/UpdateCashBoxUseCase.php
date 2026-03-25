<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use DateTimeImmutable;

final readonly class UpdateCashBoxUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository
    ) {
    }

    /**
     * @throws CashBoxNotFoundException
     */
    public function execute(
        int $boxId,
        string $name,
        ?string $description = null
    ): void {
        $box = $this->cashBoxRepository->findById($boxId);

        $updatedBox = new CashBox(
            boxId: $box->boxId,
            createdBy: $box->createdBy,
            name: $name,
            description: $description,
            currency: $box->currency,
            initialBalance: $box->initialBalance,
            currentBalance: $box->currentBalance,
            status: $box->status,
            createdAt: $box->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $box->deletedAt
        );

        $this->cashBoxRepository->save($updatedBox);
    }
}
