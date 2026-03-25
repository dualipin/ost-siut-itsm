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
                box_id, category_id, contributor_user_id, created_by, type, amount,
                balance_before, balance_after, description,
                transaction_date, created_at, updated_at, deleted_at
            ) VALUES (
                :box_id, :category_id, :contributor_user_id, :created_by, :type, :amount,
                :balance_before, :balance_after, :description,
                :transaction_date, :created_at, :updated_at, :deleted_at
            )
        ');
        
        // Note: transaction_id is omitted to use AUTO_INCREMENT

        $stmt->execute([
            'box_id' => $transaction->boxId,
            'category_id' => $transaction->categoryId,
            'contributor_user_id' => $transaction->contributorUserId,
            'created_by' => $transaction->createdBy,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'balance_before' => $transaction->balanceBefore,
            'balance_after' => $transaction->balanceAfter,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transactionDate->format('Y-m-d'),
            'created_at' => $transaction->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $transaction->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $transaction->deletedAt?->format('Y-m-d H:i:s'),
        ]);

        $transactionId = (int) $this->pdo->lastInsertId();
        if ($transactionId > 0 && $transaction->attachments !== []) {
            $attachmentStmt = $this->pdo->prepare('
                INSERT INTO box_transaction_attachments (transaction_id, file_path, mime_type, description)
                VALUES (:transaction_id, :file_path, :mime_type, :description)
            ');

            foreach ($transaction->attachments as $filePath) {
                if (!is_string($filePath) || $filePath === '') {
                    continue;
                }

                $attachmentStmt->execute([
                    'transaction_id' => $transactionId,
                    'file_path' => $filePath,
                    'mime_type' => $this->resolveMimeTypeFromPath($filePath),
                    'description' => null,
                ]);
            }
        }
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
        return $this->findByCriteria($boxId);
    }

    public function findByCriteria(?int $boxId = null, ?string $type = null, ?int $categoryId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $where = ['t.deleted_at IS NULL'];
        $params = [];

        if ($boxId !== null) {
            $where[] = 't.box_id = :box_id';
            $params['box_id'] = $boxId;
        }

        if ($type !== null && $type !== '') {
            $where[] = 't.type = :type';
            $params['type'] = $type;
        }

        if ($categoryId !== null) {
            $where[] = 't.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($startDate !== null && $startDate !== '') {
            $where[] = 't.transaction_date >= :start_date';
            $params['start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $where[] = 't.transaction_date <= :end_date';
            $params['end_date'] = $endDate;
        }

        $sql = 'SELECT t.* FROM box_transactions t WHERE ' . implode(' AND ', $where) . ' ORDER BY t.transaction_date DESC, t.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $attachmentsByTransactionId = [];
        if ($rows !== []) {
            $transactionIds = array_map(static fn (array $row): int => (int) $row['transaction_id'], $rows);
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));

            $attachmentStmt = $this->pdo->prepare(
                "SELECT transaction_id, file_path
                 FROM box_transaction_attachments
                 WHERE transaction_id IN ({$placeholders})
                 ORDER BY attachment_id ASC"
            );
            $attachmentStmt->execute($transactionIds);

            foreach ($attachmentStmt->fetchAll(PDO::FETCH_ASSOC) as $attachmentRow) {
                $transactionId = (int) $attachmentRow['transaction_id'];
                if (!array_key_exists($transactionId, $attachmentsByTransactionId)) {
                    $attachmentsByTransactionId[$transactionId] = [];
                }
                $attachmentsByTransactionId[$transactionId][] = (string) $attachmentRow['file_path'];
            }
        }

        return $this->hydrateTransactions($rows, $attachmentsByTransactionId);
    }

    public function summarizeByPeriod(string $startDate, string $endDate, ?int $boxId = null): array
    {
        $where = ['t.deleted_at IS NULL', 't.transaction_date BETWEEN :start_date AND :end_date'];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($boxId !== null) {
            $where[] = 't.box_id = :box_id';
            $params['box_id'] = $boxId;
        }

        $sql = '
            SELECT
                DATE_FORMAT(t.transaction_date, "%Y-%m") AS month,
                SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS total_income,
                SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS total_expense
            FROM box_transactions t
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY DATE_FORMAT(t.transaction_date, "%Y-%m")
            ORDER BY month ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'month' => (string) $row['month'],
                'total_income' => (float) $row['total_income'],
                'total_expense' => (float) $row['total_expense'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function totalsByContributionCategory(string $startDate, string $endDate, ?int $boxId = null): array
    {
        $where = [
            't.deleted_at IS NULL',
            't.type = "income"',
            't.transaction_date BETWEEN :start_date AND :end_date',
        ];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($boxId !== null) {
            $where[] = 't.box_id = :box_id';
            $params['box_id'] = $boxId;
        }

        $sql = '
            SELECT
                COALESCE(c.contribution_category, "other") AS contribution_category,
                c.name AS category_name,
                SUM(t.amount) AS total_amount
            FROM box_transactions t
            INNER JOIN transaction_categories c ON c.category_id = t.category_id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY COALESCE(c.contribution_category, "other"), c.name
            ORDER BY total_amount DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'contribution_category' => (string) $row['contribution_category'],
                'category_name' => (string) $row['category_name'],
                'total_amount' => (float) $row['total_amount'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<int, string>> $attachmentsByTransactionId
     * @return array<int, BoxTransaction>
     */
    private function hydrateTransactions(array $rows, array $attachmentsByTransactionId): array
    {
        $transactions = [];
        foreach ($rows as $row) {
            $transactionId = (int) $row['transaction_id'];
            $attachments = $attachmentsByTransactionId[$transactionId] ?? [];

            $transactions[] = new BoxTransaction(
                transactionId: $transactionId,
                boxId: (int)$row['box_id'],
                categoryId: (int)$row['category_id'],
                contributorUserId: isset($row['contributor_user_id']) ? (int) $row['contributor_user_id'] : null,
                createdBy: (int)$row['created_by'],
                type: TransactionTypeEnum::from($row['type']),
                amount: (float)$row['amount'],
                balanceBefore: (float)$row['balance_before'],
                balanceAfter: (float)$row['balance_after'],
                referenceNumber: null,
                description: $row['description'],
                transactionDate: new DateTimeImmutable($row['transaction_date']),
                createdAt: new DateTimeImmutable($row['created_at']),
                attachments: $attachments,
                attachment: $attachments[0] ?? null,
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

    private function resolveMimeTypeFromPath(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }
}
