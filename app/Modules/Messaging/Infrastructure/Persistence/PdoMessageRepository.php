<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Messaging\Domain\Entity\Message;
use App\Modules\Messaging\Domain\Repository\MessageRepositoryInterface;

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
}
