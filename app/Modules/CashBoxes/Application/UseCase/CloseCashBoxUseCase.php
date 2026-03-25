<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\CashBoxes\Domain\Entity\BoxClosing;
use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CloseCashBoxUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository,
        // Assuming a ClosingRepository or similar, but for now we can just have a simple design
        // Or we use TransactionManager and a separate Repository for Closings.
        // Let's add it to TransactionRepository for simplicity or assume it exists.
        private TransactionManager $transactionManager
    ) {
    }

    /**
     * @throws CashBoxNotFoundException
     * @throws InvalidArgumentException
     */
    public function execute(
        int $boxId,
        int $closedBy,
        float $actualBalance,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?string $notes = null
    ): void {
        $this->transactionManager->transactional(function () use (
            $boxId,
            $closedBy,
            $actualBalance,
            $periodStart,
            $periodEnd,
            $notes
        ) {
            $box = $this->cashBoxRepository->findById($boxId);

            if (!$box->isOpen()) {
                throw new InvalidArgumentException("Cash box is already closed.");
            }

            $expectedBalance = $box->currentBalance;
            $difference = $actualBalance - $expectedBalance;

            $now = new DateTimeImmutable();

            // Note: Saving to a BoxClosingRepository would go here.
            // For now, we mainly update the box status.

            $updatedBox = new CashBox(
                boxId: $box->boxId,
                createdBy: $box->createdBy,
                name: $box->name,
                description: $box->description,
                currency: $box->currency,
                initialBalance: $box->initialBalance,
                currentBalance: $expectedBalance, // Balance remains the system balance
                status: BoxStatusEnum::CLOSED,
                createdAt: $box->createdAt,
                updatedAt: $now,
                deletedAt: $box->deletedAt
            );

            $this->cashBoxRepository->save($updatedBox);
        });
    }
}
