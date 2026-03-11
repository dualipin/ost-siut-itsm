<?php

namespace App\Infrastructure\Mail;

use DateTimeImmutable;
use PDO;

final readonly class PdoMailQueueRepository
{
    public function __construct(private PDO $pdo) {}

    public function releaseStaleLocks(int $lockTimeoutMinutes): int
    {
        $minutes = max(1, $lockTimeoutMinutes);
        $threshold = (new DateTimeImmutable("-{$minutes} minutes"))->format(
            "Y-m-d H:i:s",
        );

        $stmt = $this->pdo->prepare(
            "UPDATE mail_queue
            SET status = 'pending',
                locked_at = NULL,
                lock_token = NULL
            WHERE status = 'sending'
              AND locked_at IS NOT NULL
              AND locked_at < :threshold",
        );

        $stmt->execute([":threshold" => $threshold]);

        return $stmt->rowCount();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claimBatch(
        string $processToken,
        int $batchSize,
        int $defaultMaxAttempts,
    ): array {
        $size = max(1, $batchSize);

        $claimSql = sprintf(
            "UPDATE mail_queue
            SET status = 'sending',
                locked_at = NOW(),
                lock_token = :processToken
            WHERE status = 'pending'
              AND scheduled_at <= NOW()
              AND attempts < COALESCE(max_attempts, :defaultMaxAttempts)
            ORDER BY priority ASC, scheduled_at ASC, created_at ASC
            LIMIT %d",
            $size,
        );

        $claimStmt = $this->pdo->prepare($claimSql);
        $claimStmt->execute([
            ":processToken" => $processToken,
            ":defaultMaxAttempts" => $defaultMaxAttempts,
        ]);

        if ($claimStmt->rowCount() === 0) {
            return [];
        }

        $selectStmt = $this->pdo->prepare(
            "SELECT *
            FROM mail_queue
            WHERE status = 'sending'
              AND lock_token = :processToken
            ORDER BY priority ASC, scheduled_at ASC, created_at ASC",
        );
        $selectStmt->execute([":processToken" => $processToken]);

        return $selectStmt->fetchAll();
    }

    public function markAsSent(int $id, string $processToken): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE mail_queue
            SET status = 'sent',
                locked_at = NULL,
                lock_token = NULL,
                last_error = NULL
            WHERE id = :id
              AND status = 'sending'
              AND lock_token = :processToken",
        );
        $stmt->execute([
            ":id" => $id,
            ":processToken" => $processToken,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function markAsFailedOrPending(
        int $id,
        string $processToken,
        string $errorMessage,
        int $defaultMaxAttempts,
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE mail_queue
            SET status = CASE
                    WHEN attempts + 1 >= COALESCE(max_attempts, :defaultMaxAttempts) THEN 'failed'
                    ELSE 'pending'
                END,
                attempts = attempts + 1,
                last_error = :lastError,
                locked_at = NULL,
                lock_token = NULL
            WHERE id = :id
              AND status = 'sending'
              AND lock_token = :processToken",
        );
        $stmt->execute([
            ":id" => $id,
            ":processToken" => $processToken,
            ":lastError" => $errorMessage,
            ":defaultMaxAttempts" => $defaultMaxAttempts,
        ]);

        return $stmt->rowCount() === 1;
    }
}