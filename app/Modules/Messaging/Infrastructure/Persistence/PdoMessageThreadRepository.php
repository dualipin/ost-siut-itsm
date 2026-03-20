<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;

final class PdoMessageThreadRepository extends PdoBaseRepository implements MessageThreadRepositoryInterface
{
    public function create(MessageThread $thread): int
    {
        $stmt = $this->pdo->prepare(
            "
            INSERT INTO message_threads (
                thread_type,
                sender_id,
                external_name,
                external_email,
                external_phone,
                recipient_id,
                subject,
                status,
                visibility,
                assigned_to,
                external_channel
            ) VALUES (
                :thread_type,
                :sender_id,
                :external_name,
                :external_email,
                :external_phone,
                :recipient_id,
                :subject,
                :status,
                :visibility,
                :assigned_to,
                :external_channel
            )
            ",
        );

        $stmt->execute([
            ":thread_type" => $thread->threadType->value,
            ":sender_id" => $thread->senderId,
            ":external_name" => $thread->externalName,
            ":external_email" => $thread->externalEmail,
            ":external_phone" => $thread->externalPhone,
            ":recipient_id" => $thread->recipientId,
            ":subject" => $thread->subject,
            ":status" => $thread->status->value,
            ":visibility" => $thread->visibility->value,
            ":assigned_to" => $thread->assignedTo,
            ":external_channel" => $thread->externalChannel?->value,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
