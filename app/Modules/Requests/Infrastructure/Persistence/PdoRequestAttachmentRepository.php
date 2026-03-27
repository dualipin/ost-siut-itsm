<?php

declare(strict_types=1);

namespace App\Modules\Requests\Infrastructure\Persistence;

use App\Modules\Requests\Domain\Entity\RequestAttachment;
use App\Modules\Requests\Domain\Repository\RequestAttachmentRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoRequestAttachmentRepository implements RequestAttachmentRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(RequestAttachment $attachment): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO request_attachments (request_id, file_path, mime_type, description, uploaded_at)
            VALUES (:request_id, :file_path, :mime_type, :description, :uploaded_at)
        ');
        $stmt->execute([
            'request_id'  => $attachment->requestId,
            'file_path'   => $attachment->filePath,
            'mime_type'   => $attachment->mimeType,
            'description' => $attachment->description,
            'uploaded_at' => $attachment->uploadedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByRequestId(int $requestId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM request_attachments WHERE request_id = :request_id ORDER BY uploaded_at ASC'
        );
        $stmt->execute(['request_id' => $requestId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new RequestAttachment(
            attachmentId: (int)$row['attachment_id'],
            requestId:    (int)$row['request_id'],
            filePath:     $row['file_path'],
            mimeType:     $row['mime_type'],
            description:  $row['description'],
            uploadedAt:   new DateTimeImmutable($row['uploaded_at']),
        ), $rows);
    }
}
