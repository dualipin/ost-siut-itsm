<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\CashBoxes\Domain\Entity\BoxTransaction;
use App\Modules\CashBoxes\Domain\Entity\CashBox;
use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use App\Modules\CashBoxes\Domain\Exception\CashBoxNotFoundException;
use App\Modules\CashBoxes\Domain\Exception\InsufficientFundsException;
use App\Modules\CashBoxes\Domain\Repository\CashBoxRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecordTransactionUseCase
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
        int $boxId,
        int $categoryId,
        int $createdBy,
        string $type,
        float $amount,
        ?string $referenceNumber = null,
        ?string $description = null
    ): void {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be greater than zero.");
        }

        $typeEnum = TransactionTypeEnum::from($type);

        $this->transactionManager->transactional(function () use (
            $boxId,
            $categoryId,
            $createdBy,
            $typeEnum,
            $amount,
            $referenceNumber,
            $description
        ) {
            $box = $this->cashBoxRepository->findById($boxId);
            
            if (!$box->isOpen()) {
                throw new InvalidArgumentException("Cannot record transaction in a closed box.");
            }

            $balanceBefore = $box->currentBalance;

            if ($typeEnum === TransactionTypeEnum::EXPENSE && $balanceBefore < $amount) {
                throw new InsufficientFundsException();
            }

            $balanceAfter = $typeEnum === TransactionTypeEnum::INCOME 
                ? $balanceBefore + $amount 
                : $balanceBefore - $amount;

            $now = new DateTimeImmutable();

            $transaction = new BoxTransaction(
                transactionId: $this->transactionRepository->nextTransactionId(),
                boxId: $boxId,
                categoryId: $categoryId,
                createdBy: $createdBy,
                type: $typeEnum,
                amount: $amount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                referenceNumber: $referenceNumber,
                description: $description,
                transactionDate: $now,
                createdAt: $now
            );

            $updatedBox = new CashBox(
                boxId: $box->boxId,
                createdBy: $box->createdBy,
                name: $box->name,
                description: $box->description,
                currency: $box->currency,
                initialBalance: $box->initialBalance,
                currentBalance: $balanceAfter,
                status: $box->status,
                createdAt: $box->createdAt,
                updatedAt: $now,
                deletedAt: $box->deletedAt
            );

            $this->transactionRepository->saveTransaction($transaction);
            $this->cashBoxRepository->save($updatedBox);
        });
    }
}
