<?php

namespace App\Modules\Publication\Domain\Repository;

use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Entity\PublicationAttachment;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;

interface PublicationRepositoryInterface
{
    /**
     * @return Publication[]
     */
    public function findByType(PublicationTypeEnum $type): array;
    public function findById(int $id): ?Publication;
    public function create(Publication $publication): int;
    public function update(Publication $publication): void;

    /**
     * @param PublicationAttachment[] $attachments
     */
    public function addAttachments(int $publicationId, array $attachments): void;
    public function deleteById(int $id): void;
}
