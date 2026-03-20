<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\Repository;

/**
 * Public content visible to no_agremiado (non-members/guest users).
 */
interface PublicDashboardRepositoryInterface
{
    /**
     * Recent public publications (no expiration or expiration_date > TODAY).
     * Returns array of {publication_id, title, summary, thumbnail_url, publication_type, created_at}.
     */
    public function getPublicPublications(int $limit = 4): array;

    /**
     * Public FAQs / Q&A threads (visibility = 'public' and type = 'qa').
     * Returns array of {thread_id, subject, question, answer, created_at}.
     */
    public function getPublicFaqs(int $limit = 10): array;

    /**
     * Public transparency documents (is_private = FALSE).
     * Returns array of {transparency_id, title, transparency_type, date_published}.
     */
    public function getPublicTransparencyDocuments(int $limit = 5): array;
}
