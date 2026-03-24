<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\CashBoxes\Domain\Entity\BoxTransfer;
use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;
use App\Modules\CashBoxes\Domain\Exception\InsufficientFundsException;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TransferFundsUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private TransactionManager $transactionManager
    ) {
    }

    /**
     * @throws CashBoxNotFoundException
     * @throws InsufficientFundsException
     * @throws InvalidArgumentException
     */
    public function execute(
        int $sourceBoxId,
        int $destinationBoxId,
        int $createdBy,
        float $amount,
        ?string $notes = null
    ): void {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Transfer amount must be greater than zero.");
        }
        if ($sourceBoxId === $destinationBoxId) {
            throw new InvalidArgumentException("Cannot transfer to the same box.");
        }

        $this->transactionManager->transactional(function () use (
            $sourceBoxId,
            $destinationBoxId,
            $createdBy,
            $amount,
            $notes
        ) {
            $sourceBox = $this->cashBoxRepository->findById($sourceBoxId);
            $destinationBox = $this->cashBoxRepository->findById($destinationBoxId);

            if (!$sourceBox->isOpen() || !$destinationBox->isOpen()) {
                throw new InvalidArgumentException("Both boxes must be open for a transfer.");
            }
            if ($sourceBox->currency !== $destinationBox->currency) {
                throw new InvalidArgumentException("Boxes must have the same currency to transfer.");
            }
            if ($sourceBox->currentBalance < $amount) {
                throw new InsufficientFundsException("Insufficient funds in the source box.");
            }

            $sourceBalanceBefore = $sourceBox->currentBalance;
            $sourceBalanceAfter = $sourceBalanceBefore - $amount;

            $destBalanceBefore = $destinationBox->currentBalance;
            $destBalanceAfter = $destBalanceBefore + $amount;

            $now = new DateTimeImmutable();

            $transfer = new BoxTransfer(
                transferId: $this->transactionRepository->nextTransferId(),
                sourceBoxId: $sourceBoxId,
                destinationBoxId: $destinationBoxId,
                createdBy: $createdBy,
                amount: $amount,
                sourceBalanceBefore: $sourceBalanceBefore,
                sourceBalanceAfter: $sourceBalanceAfter,
                destinationBalanceBefore: $destBalanceBefore,
                destinationBalanceAfter: $destBalanceAfter,
                notes: $notes,
                transferredAt: $now
            );

            $updatedSourceBox = new CashBox(
                boxId: $sourceBox->boxId,
                createdBy: $sourceBox->createdBy,
                name: $sourceBox->name,
                description: $sourceBox->description,
                currency: $sourceBox->currency,
                initialBalance: $sourceBox->initialBalance,
                currentBalance: $sourceBalanceAfter,
                status: $sourceBox->status,
                createdAt: $sourceBox->createdAt,
                updatedAt: $now,
                deletedAt: $sourceBox->deletedAt
            );

            $updatedDestBox = new CashBox(
                boxId: $destinationBox->boxId,
                createdBy: $destinationBox->createdBy,
                name: $destinationBox->name,
                description: $destinationBox->description,
                currency: $destinationBox->currency,
                initialBalance: $destinationBox->initialBalance,
                currentBalance: $destBalanceAfter,
                status: $destinationBox->status,
                createdAt: $destinationBox->createdAt,
                updatedAt: $now,
                deletedAt: $destinationBox->deletedAt
            );

            $this->transactionRepository->saveTransfer($transfer);
            $this->cashBoxRepository->save($updatedSourceBox);
            $this->cashBoxRepository->save($updatedDestBox);
        });
    }
}
