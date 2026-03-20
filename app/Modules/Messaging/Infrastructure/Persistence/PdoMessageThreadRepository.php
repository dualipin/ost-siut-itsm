<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Messaging\Domain\Entity\MessageThread;
use App\Modules\Messaging\Domain\Enum\ExternalChannel;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;
use App\Modules\Messaging\Domain\Repository\MessageThreadRepositoryInterface;
use DateTimeImmutable;

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

    /** @return array<int, array<string, mixed>> */
    public function findByType(ThreadType $type): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                mt.thread_id,
                mt.subject,
                mt.status,
                mt.external_name,
                mt.external_email,
                mt.external_phone,
                mt.created_at,
                mt.updated_at,
                m.body AS first_message
            FROM message_threads mt
            LEFT JOIN (
                SELECT thread_id, body
                FROM messages
                WHERE deleted_at IS NULL
                ORDER BY sent_at ASC
                LIMIT 1
            ) m ON m.thread_id = mt.thread_id
            WHERE mt.thread_type = :thread_type
              AND mt.deleted_at IS NULL
            ORDER BY mt.created_at DESC
            ",
        );

        $stmt->execute([':thread_type' => $type->value]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?MessageThread
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT *
            FROM message_threads
            WHERE thread_id = :id
              AND deleted_at IS NULL
            ",
        );

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new MessageThread(
            id: (int) $row['thread_id'],
            threadType: ThreadType::from($row['thread_type']),
            status: ThreadStatus::from($row['status']),
            visibility: ThreadVisibility::from($row['visibility']),
            senderId: $row['sender_id'] !== null ? (int) $row['sender_id'] : null,
            externalName: $row['external_name'],
            externalEmail: $row['external_email'],
            externalPhone: $row['external_phone'],
            recipientId: $row['recipient_id'] !== null ? (int) $row['recipient_id'] : null,
            subject: $row['subject'],
            assignedTo: $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
            externalChannel: $row['external_channel'] !== null
                ? ExternalChannel::from($row['external_channel'])
                : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            deletedAt: $row['deleted_at'] !== null
                ? new DateTimeImmutable($row['deleted_at'])
                : null,
        );
    }

    public function updateStatus(int $id, ThreadStatus $status): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE message_threads SET status = :status WHERE thread_id = :id",
        );

        $stmt->execute([
            ':status' => $status->value,
            ':id' => $id,
        ]);
    }

    public function updateAssignedTo(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE message_threads SET assigned_to = :user_id WHERE thread_id = :id",
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':id' => $id,
        ]);
    }
}

