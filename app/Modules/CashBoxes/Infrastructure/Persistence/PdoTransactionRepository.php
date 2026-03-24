<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Infrastructure\Persistence;

use App\Modules\CashBoxes\Domain\Entity\BoxTransaction;
use App\Modules\CashBoxes\Domain\Entity\BoxTransfer;
use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use App\Modules\CashBoxes\Domain\Repository\TransactionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function saveTransaction(BoxTransaction $transaction): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO box_transactions (
                box_id, category_id, created_by, type, amount,
                balance_before, balance_after, reference_number, description,
                transaction_date, created_at, updated_at, deleted_at
            ) VALUES (
                :box_id, :category_id, :created_by, :type, :amount,
                :balance_before, :balance_after, :reference_number, :description,
                :transaction_date, :created_at, :updated_at, :deleted_at
            )
        ');
        
        // Note: transaction_id is omitted to use AUTO_INCREMENT

        $stmt->execute([
            'box_id' => $transaction->boxId,
            'category_id' => $transaction->categoryId,
            'created_by' => $transaction->createdBy,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'balance_before' => $transaction->balanceBefore,
            'balance_after' => $transaction->balanceAfter,
            'reference_number' => $transaction->referenceNumber,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transactionDate->format('Y-m-d'),
            'created_at' => $transaction->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $transaction->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $transaction->deletedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function saveTransfer(BoxTransfer $transfer): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO box_transfers (
                source_box_id, destination_box_id, created_by, amount,
                source_balance_before, source_balance_after, destination_balance_before,
                destination_balance_after, notes, transferred_at
            ) VALUES (
                :source_box_id, :destination_box_id, :created_by, :amount,
                :source_balance_before, :source_balance_after, :destination_balance_before,
                :destination_balance_after, :notes, :transferred_at
            )
        ');

        $stmt->execute([
            'source_box_id' => $transfer->sourceBoxId,
            'destination_box_id' => $transfer->destinationBoxId,
            'created_by' => $transfer->createdBy,
            'amount' => $transfer->amount,
            'source_balance_before' => $transfer->sourceBalanceBefore,
            'source_balance_after' => $transfer->sourceBalanceAfter,
            'destination_balance_before' => $transfer->destinationBalanceBefore,
            'destination_balance_after' => $transfer->destinationBalanceAfter,
            'notes' => $transfer->notes,
            'transferred_at' => $transfer->transferredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByBoxId(int $boxId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM box_transactions WHERE box_id = :box_id AND deleted_at IS NULL ORDER BY created_at DESC');
        $stmt->execute(['box_id' => $boxId]);
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $transactions = [];
        foreach ($rows as $row) {
            $transactions[] = new BoxTransaction(
                transactionId: (int)$row['transaction_id'],
                boxId: (int)$row['box_id'],
                categoryId: (int)$row['category_id'],
                createdBy: (int)$row['created_by'],
                type: TransactionTypeEnum::from($row['type']),
                amount: (float)$row['amount'],
                balanceBefore: (float)$row['balance_before'],
                balanceAfter: (float)$row['balance_after'],
                referenceNumber: $row['reference_number'],
                description: $row['description'],
                transactionDate: new DateTimeImmutable($row['transaction_date']),
                createdAt: new DateTimeImmutable($row['created_at']),
                updatedAt: $row['updated_at'] ? new DateTimeImmutable($row['updated_at']) : null,
                deletedAt: $row['deleted_at'] ? new DateTimeImmutable($row['deleted_at']) : null,
            );
        }
        return $transactions;
    }

    public function nextTransactionId(): int
    {
        return 0; // Handled by AUTO_INCREMENT
    }

    public function nextTransferId(): int
    {
        return 0; // Handled by AUTO_INCREMENT
    }
}
