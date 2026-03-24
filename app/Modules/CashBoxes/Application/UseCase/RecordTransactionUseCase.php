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
use App\Modules\CashBoxes\Domain\Repository\CategoryRepositoryInterface;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecordTransactionUseCase
{
    public function __construct(
        private CashBoxRepositoryInterface $cashBoxRepository,
        private CategoryRepositoryInterface $categoryRepository,
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
        ?string $description = null,
        ?int $contributorUserId = null,
        array $attachments = []
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
            $description,
            $contributorUserId,
            $attachments
        ) {
            $box = $this->cashBoxRepository->findById($boxId);

            $category = $this->categoryRepository->findById($categoryId);
            if ($category === null) {
                throw new InvalidArgumentException("La categoría seleccionada no existe.");
            }
            if ($category->type !== $typeEnum) {
                throw new InvalidArgumentException("La categoría no coincide con el tipo de movimiento.");
            }
            
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
                contributorUserId: $contributorUserId,
                createdBy: $createdBy,
                type: $typeEnum,
                amount: $amount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                description: $description,
                transactionDate: $now,
                createdAt: $now,
                attachments: $attachments,
                attachment: $attachments[0] ?? null
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
