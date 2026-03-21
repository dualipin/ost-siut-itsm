<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Infrastructure\Persistence;

use App\Modules\Transparency\Domain\Entity\Transparency;
use App\Modules\Transparency\Domain\Entity\TransparencyAttachment;
use App\Modules\Transparency\Domain\Entity\TransparencyPermission;
use App\Modules\Transparency\Domain\Enum\AttachmentType;
use App\Modules\Transparency\Domain\Enum\TransparencyType;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoTransparencyRepository implements TransparencyRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function findAllPublic(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM transparency 
            WHERE is_private = 0 
            ORDER BY date_published DESC, created_at DESC
        ');
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllPublicByType(TransparencyType $type): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM transparency 
            WHERE is_private = 0 AND transparency_type = :type
            ORDER BY date_published DESC, created_at DESC
        ');
        $stmt->execute(['type' => $type->value]);
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllPermittedForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.* 
            FROM transparency t
            LEFT JOIN transparency_permissions tp ON t.transparency_id = tp.transparency_id
            WHERE t.is_private = 0 OR t.author_id = :authorId OR tp.user_id = :userId
            GROUP BY t.transparency_id
            ORDER BY t.date_published DESC, t.created_at DESC
        ');
        $stmt->execute(['authorId' => $userId, 'userId' => $userId]);
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllPublicOrPermittedForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.* 
            FROM transparency t
            LEFT JOIN transparency_permissions tp ON t.transparency_id = tp.transparency_id
            WHERE t.is_private = 0 OR tp.user_id = :userId
            GROUP BY t.transparency_id
            ORDER BY t.date_published DESC, t.created_at DESC
        ');
        $stmt->execute(['userId' => $userId]);
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllPermittedForUserByType(int $userId, TransparencyType $type): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.* 
            FROM transparency t
            LEFT JOIN transparency_permissions tp ON t.transparency_id = tp.transparency_id
            WHERE (t.is_private = 0 OR t.author_id = :authorId OR tp.user_id = :userId)
              AND t.transparency_type = :type
            GROUP BY t.transparency_id
            ORDER BY t.date_published DESC, t.created_at DESC
        ');
        $stmt->execute(['authorId' => $userId, 'userId' => $userId, 'type' => $type->value]);
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllPublicOrPermittedForUserByType(int $userId, TransparencyType $type): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.* 
            FROM transparency t
            LEFT JOIN transparency_permissions tp ON t.transparency_id = tp.transparency_id
            WHERE (t.is_private = 0 OR tp.user_id = :userId)
              AND t.transparency_type = :type
            GROUP BY t.transparency_id
            ORDER BY t.date_published DESC, t.created_at DESC
        ');
        $stmt->execute(['userId' => $userId, 'type' => $type->value]);
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM transparency ORDER BY date_published DESC, created_at DESC');
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllByType(TransparencyType $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transparency WHERE transparency_type = :type ORDER BY date_published DESC, created_at DESC');
        $stmt->execute(['type' => $type->value]);
        return array_map([$this, 'hydrateTransparency'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Transparency
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transparency WHERE transparency_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateTransparency($row);
    }

    public function save(Transparency $transparency): Transparency
    {
        if ($transparency->id === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO transparency (author_id, title, summary, transparency_type, date_published, is_private, created_at)
                VALUES (:author_id, :title, :summary, :type, :date_published, :is_private, :created_at)
            ');
            $stmt->execute([
                'author_id' => $transparency->authorId,
                'title' => $transparency->title,
                'summary' => $transparency->summary,
                'type' => $transparency->type->value,
                'date_published' => $transparency->datePublished->format('Y-m-d'),
                'is_private' => (int) $transparency->isPrivate,
                'created_at' => $transparency->createdAt->format('Y-m-d H:i:s')
            ]);

            return new Transparency(
                id: (int) $this->pdo->lastInsertId(),
                authorId: $transparency->authorId,
                title: $transparency->title,
                summary: $transparency->summary,
                type: $transparency->type,
                createdAt: $transparency->createdAt,
                datePublished: $transparency->datePublished,
                isPrivate: $transparency->isPrivate
            );
        }

        $stmt = $this->pdo->prepare('
            UPDATE transparency 
            SET title = :title, 
                summary = :summary, 
                transparency_type = :type, 
                date_published = :date_published, 
                is_private = :is_private
            WHERE transparency_id = :id
        ');
        $stmt->execute([
            'id' => $transparency->id,
            'title' => $transparency->title,
            'summary' => $transparency->summary,
            'type' => $transparency->type->value,
            'date_published' => $transparency->datePublished->format('Y-m-d'),
            'is_private' => (int) $transparency->isPrivate
        ]);

        return $transparency;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM transparency WHERE transparency_id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function saveAttachment(TransparencyAttachment $attachment): TransparencyAttachment
    {
        if ($attachment->id === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO transparency_attachments (transparency_id, file_path, mime_type, attachment_type, description)
                VALUES (:transparency_id, :file_path, :mime_type, :attachment_type, :description)
            ');
            $stmt->execute([
                'transparency_id' => $attachment->transparencyId,
                'file_path' => $attachment->filePath,
                'mime_type' => $attachment->mimeType,
                'attachment_type' => $attachment->attachmentType->value,
                'description' => $attachment->description
            ]);

            return new TransparencyAttachment(
                id: (int) $this->pdo->lastInsertId(),
                transparencyId: $attachment->transparencyId,
                filePath: $attachment->filePath,
                mimeType: $attachment->mimeType,
                attachmentType: $attachment->attachmentType,
                description: $attachment->description
            );
        }

        $stmt = $this->pdo->prepare('
            UPDATE transparency_attachments 
            SET file_path = :file_path, 
                mime_type = :mime_type, 
                attachment_type = :attachment_type, 
                description = :description
            WHERE attachment_id = :id
        ');
        $stmt->execute([
            'id' => $attachment->id,
            'file_path' => $attachment->filePath,
            'mime_type' => $attachment->mimeType,
            'attachment_type' => $attachment->attachmentType->value,
            'description' => $attachment->description
        ]);

        return $attachment;
    }

    public function findAttachmentsByTransparencyId(int $transparencyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transparency_attachments WHERE transparency_id = :id');
        $stmt->execute(['id' => $transparencyId]);
        return array_map([$this, 'hydrateAttachment'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM transparency_attachments WHERE attachment_id = :id');
        $stmt->execute(['id' => $attachmentId]);
    }

    public function grantPermission(TransparencyPermission $permission): void
    {
        $stmt = $this->pdo->prepare('
            INSERT IGNORE INTO transparency_permissions (transparency_id, user_id)
            VALUES (:transparency_id, :user_id)
        ');
        $stmt->execute([
            'transparency_id' => $permission->transparencyId,
            'user_id' => $permission->userId
        ]);
    }

    public function revokePermission(int $transparencyId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM transparency_permissions 
            WHERE transparency_id = :transparency_id AND user_id = :user_id
        ');
        $stmt->execute([
            'transparency_id' => $transparencyId,
            'user_id' => $userId
        ]);
    }

    public function findPermissionsByTransparencyId(int $transparencyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transparency_permissions WHERE transparency_id = :id');
        $stmt->execute(['id' => $transparencyId]);
        
        $permissions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $permissions[] = new TransparencyPermission(
                id: (int) $row['permission_id'],
                transparencyId: (int) $row['transparency_id'],
                userId: (int) $row['user_id']
            );
        }
        return $permissions;
    }

    private function hydrateTransparency(array $row): Transparency
    {
        return new Transparency(
            id: (int) $row['transparency_id'],
            authorId: (int) $row['author_id'],
            title: $row['title'],
            summary: $row['summary'],
            type: TransparencyType::from($row['transparency_type']),
            createdAt: new DateTimeImmutable($row['created_at']),
            datePublished: new DateTimeImmutable($row['date_published']),
            isPrivate: (bool) $row['is_private']
        );
    }

    private function hydrateAttachment(array $row): TransparencyAttachment
    {
        return new TransparencyAttachment(
            id: (int) $row['attachment_id'],
            transparencyId: (int) $row['transparency_id'],
            filePath: $row['file_path'],
            mimeType: $row['mime_type'],
            attachmentType: AttachmentType::from($row['attachment_type']),
            description: $row['description']
        );
    }
}
