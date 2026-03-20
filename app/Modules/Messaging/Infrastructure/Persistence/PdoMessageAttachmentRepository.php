<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Messaging\Domain\Entity\MessageAttachment;
use App\Modules\Messaging\Domain\Repository\MessageAttachmentRepositoryInterface;
use DateTimeImmutable;

final class PdoMessageAttachmentRepository extends PdoBaseRepository implements MessageAttachmentRepositoryInterface
{
    public function create(MessageAttachment $attachment): int
    {
        $stmt = $this->pdo->prepare(
            "
            INSERT INTO message_attachments (
                message_id,
                file_path,
                file_name,
                mime_type,
                file_size
            ) VALUES (
                :message_id,
                :file_path,
                :file_name,
                :mime_type,
                :file_size
            )
            ",
        );

        $stmt->execute([
            ':message_id' => $attachment->messageId,
            ':file_path'  => $attachment->filePath,
            ':file_name'  => $attachment->fileName,
            ':mime_type'  => $attachment->mimeType,
            ':file_size'  => $attachment->fileSize,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return MessageAttachment[] */
    public function findByMessageId(int $messageId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM message_attachments WHERE message_id = :message_id ORDER BY uploaded_at ASC",
        );

        $stmt->execute([':message_id' => $messageId]);

        $attachments = [];
        foreach ($stmt->fetchAll() as $row) {
            $attachments[] = new MessageAttachment(
                id: (int) $row['attachment_id'],
                messageId: (int) $row['message_id'],
                filePath: $row['file_path'],
                fileName: $row['file_name'],
                mimeType: $row['mime_type'],
                fileSize: (int) $row['file_size'],
                uploadedAt: new DateTimeImmutable($row['uploaded_at']),
            );
        }

        return $attachments;
    }
}
