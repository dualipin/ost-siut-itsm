<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\Dashboard\Domain\Repository\PublicDashboardRepositoryInterface;

final class PdoPublicDashboardRepository extends PdoBaseRepository implements PublicDashboardRepositoryInterface
{
    public function getPublicPublications(int $limit = 4): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->query(
            "SELECT
                p.publication_id,
                p.title,
                p.summary,
                p.thumbnail_url,
                p.publication_type,
                p.created_at
            FROM publications p
            WHERE (p.expiration_date IS NULL OR p.expiration_date >= CURDATE())
            ORDER BY p.created_at DESC
            LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }

    public function getPublicFaqs(int $limit = 10): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->query(
            "SELECT
                mt.thread_id,
                mt.subject,
                (SELECT m.body FROM messages m WHERE m.thread_id = mt.thread_id AND m.sender_id IS NOT NULL LIMIT 1) as question,
                (SELECT m.body FROM messages m WHERE m.thread_id = mt.thread_id AND m.sender_id IS NULL ORDER BY m.sent_at DESC LIMIT 1) as answer,
                mt.created_at
            FROM message_threads mt
            WHERE mt.thread_type = 'qa'
            AND mt.visibility = 'public'
            AND mt.deleted_at IS NULL
            ORDER BY mt.created_at DESC
            LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }

    public function getPublicTransparencyDocuments(int $limit = 5): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->query(
            "SELECT
                t.transparency_id,
                t.title,
                t.transparency_type,
                t.date_published
            FROM transparency t
            WHERE t.is_private = 0
            ORDER BY t.date_published DESC
            LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }
}
