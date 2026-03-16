<?php

namespace App\Modules\Publication\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Entity\PublicationAttachment;
use App\Modules\Publication\Domain\Enum\PublicationAttachmentTypeEnum;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;
use DateTimeImmutable;
use PDO;
use function array_fill;
use function array_key_exists;
use function implode;
use function is_array;

final class PdoPublicationRepository extends PdoBaseRepository implements
    PublicationRepositoryInterface
{
    public function findLatest(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                publication_id,
                author_id,
                title,
                summary,
                content,
                thumbnail_url,
                publication_type,
                expiration_date,
                created_at
            FROM publications
            WHERE (expiration_date IS NULL OR expiration_date >= CURRENT_DATE())
            ORDER BY created_at DESC
            LIMIT :limit",
        );

        $limitInt = (int) $limit;
        $stmt->bindParam("limit", $limitInt, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return $this->hydratePublications($rows);
    }

    public function findByType(PublicationTypeEnum $type): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                publication_id,
                author_id,
                title,
                summary,
                content,
                thumbnail_url,
                publication_type,
                expiration_date,
                created_at
            FROM publications
            WHERE publication_type = :type
              AND (expiration_date IS NULL OR expiration_date >= CURRENT_DATE())
            ORDER BY created_at DESC",
        );

        $stmt->execute([
            "type" => $type->value,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return $this->hydratePublications($rows);
    }

    public function findById(int $id): ?Publication
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                publication_id,
                author_id,
                title,
                summary,
                content,
                thumbnail_url,
                publication_type,
                expiration_date,
                created_at
            FROM publications
            WHERE publication_id = :id
            LIMIT 1",
        );

        $stmt->execute([
            "id" => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $publicationRows = [$row];
        $publications = $this->hydratePublications($publicationRows);

        return $publications[0] ?? null;
    }

    public function create(Publication $publication): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO publications
                (author_id, title, summary, content, thumbnail_url, publication_type, expiration_date)
            VALUES
                (:author_id, :title, :summary, :content, :thumbnail_url, :publication_type, :expiration_date)",
        );

        $stmt->execute([
            "author_id" => $publication->authorId,
            "title" => $publication->title,
            "summary" => $publication->summary,
            "content" => $publication->content,
            "thumbnail_url" => $publication->thumbnailUrl,
            "publication_type" => $publication->type->value,
            "expiration_date" => $publication->expirationDate?->format("Y-m-d"),
        ]);

        $publicationId = (int) $this->pdo->lastInsertId();

        $this->addAttachments($publicationId, $publication->attachments);

        return $publicationId;
    }

    public function update(Publication $publication): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE publications
            SET
                title = :title,
                summary = :summary,
                content = :content,
                thumbnail_url = :thumbnail_url,
                publication_type = :publication_type,
                expiration_date = :expiration_date
            WHERE publication_id = :id",
        );

        $stmt->execute([
            "id" => $publication->id,
            "title" => $publication->title,
            "summary" => $publication->summary,
            "content" => $publication->content,
            "thumbnail_url" => $publication->thumbnailUrl,
            "publication_type" => $publication->type->value,
            "expiration_date" => $publication->expirationDate?->format("Y-m-d"),
        ]);
    }

    /**
     * @param PublicationAttachment[] $attachments
     */
    public function addAttachments(int $publicationId, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        $attachmentStmt = $this->pdo->prepare(
            "INSERT INTO publication_attachments
                (publication_id, file_path, mime_type, attachment_type, description)
            VALUES
                (:publication_id, :file_path, :mime_type, :attachment_type, :description)",
        );

        foreach ($attachments as $attachment) {
            $attachmentStmt->execute([
                "publication_id" => $publicationId,
                "file_path" => $attachment->filePath,
                "mime_type" => $attachment->mimeType,
                "attachment_type" => $attachment->type->value,
                "description" => $attachment->description,
            ]);
        }
    }

    /**
     * @param int[] $attachmentIds
     */
    public function deleteAttachmentsByIds(int $publicationId, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $placeholders = implode(",", array_fill(0, count($attachmentIds), "?"));

        $stmt = $this->pdo->prepare(
            "DELETE FROM publication_attachments
             WHERE publication_id = ?
               AND attachment_id IN ({$placeholders})",
        );

        $params = [$publicationId, ...$attachmentIds];
        $stmt->execute($params);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM publications WHERE publication_id = :id",
        );

        $stmt->execute([
            "id" => $id,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return Publication[]
     */
    private function hydratePublications(array $rows): array
    {
        $publicationIds = [];

        foreach ($rows as $row) {
            $publicationIds[] = (int) $row["publication_id"];
        }

        $attachmentsByPublicationId = $this->findAttachmentsByPublicationIds(
            $publicationIds,
        );

        $publications = [];

        foreach ($rows as $row) {
            $publicationId = (int) $row["publication_id"];
            $publicationType = PublicationTypeEnum::tryFrom(
                (string) $row["publication_type"],
            );

            if ($publicationType === null) {
                continue;
            }

            $publications[] = new Publication(
                id: $publicationId,
                authorId: (int) ($row["author_id"] ?? 0),
                title: (string) $row["title"],
                content: (string) $row["content"],
                type: $publicationType,
                attachments: $attachmentsByPublicationId[$publicationId] ?? [],
                thumbnailUrl: $row["thumbnail_url"] !== null
                    ? (string) $row["thumbnail_url"]
                    : null,
                summary: $row["summary"] !== null ? (string) $row["summary"] : null,
                expirationDate: $this->parseDate($row["expiration_date"] ?? null),
                createdAt: $this->parseDateTime($row["created_at"] ?? null),
            );
        }

        return $publications;
    }

    /**
     * @param int[] $publicationIds
     * @return array<int, PublicationAttachment[]>
     */
    private function findAttachmentsByPublicationIds(array $publicationIds): array
    {
        if ($publicationIds === []) {
            return [];
        }

        $placeholders = implode(",", array_fill(0, count($publicationIds), "?"));
        $stmt = $this->pdo->prepare(
            "SELECT
                attachment_id,
                publication_id,
                file_path,
                mime_type,
                attachment_type,
                description
            FROM publication_attachments
            WHERE publication_id IN ({$placeholders})
            ORDER BY attachment_id ASC",
        );

        $stmt->execute($publicationIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $attachmentsByPublicationId = [];

        foreach ($rows as $row) {
            $publicationId = (int) $row["publication_id"];
            $attachmentType = PublicationAttachmentTypeEnum::tryFrom(
                (string) $row["attachment_type"],
            );

            if ($attachmentType === null) {
                continue;
            }

            if (!array_key_exists($publicationId, $attachmentsByPublicationId)) {
                $attachmentsByPublicationId[$publicationId] = [];
            }

            $attachmentsByPublicationId[$publicationId][] = new PublicationAttachment(
                id: isset($row["attachment_id"])
                    ? (int) $row["attachment_id"]
                    : null,
                publicationId: $publicationId,
                filePath: (string) $row["file_path"],
                mimeType: (string) $row["mime_type"],
                type: $attachmentType,
                description: $row["description"] !== null
                    ? (string) $row["description"]
                    : null,
            );
        }

        return $attachmentsByPublicationId;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === "") {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === "") {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
