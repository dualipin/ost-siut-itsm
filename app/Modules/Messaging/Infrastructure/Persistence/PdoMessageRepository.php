<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;
use DateTimeImmutable;

final class PdoMessageRepository extends PdoBaseRepository implements MessageRepositoryInterface
{
    public function create(Message $message): int
    {
        $stmt = $this->pdo->prepare(
            "
            INSERT INTO messages (
                thread_id,
                sender_id,
                body
            ) VALUES (
                :thread_id,
                :sender_id,
                :body
            )
            ",
        );

        $stmt->execute([
            ":thread_id" => $message->threadId,
            ":sender_id" => $message->senderId,
            ":body" => $message->body,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return Message[] */
    public function findByThreadId(int $threadId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT *
            FROM messages
            WHERE thread_id = :thread_id
              AND deleted_at IS NULL
            ORDER BY sent_at ASC
            ",
        );

        $stmt->execute([':thread_id' => $threadId]);

        $messages = [];

        foreach ($stmt->fetchAll() as $row) {
            $messages[] = new Message(
                id: (int) $row['message_id'],
                threadId: (int) $row['thread_id'],
                body: $row['body'],
                senderId: $row['sender_id'] !== null ? (int) $row['sender_id'] : null,
                sentAt: new DateTimeImmutable($row['sent_at']),
                readAt: $row['read_at'] !== null ? new DateTimeImmutable($row['read_at']) : null,
                deletedAt: $row['deleted_at'] !== null ? new DateTimeImmutable($row['deleted_at']) : null,
            );
        }

        return $messages;
    }
}

