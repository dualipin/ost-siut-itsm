<?php

namespace App\Modules\Publication\Infrastructure\Persistence;

use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

final readonly class PdoPublicationRepository implements
    PublicationRepositoryInterface
{
    public function findByType(PublicationTypeEnum $type): array
    {
        $sql =
            "SELECT publication_id FROM publications where publication_type = :type";

        return [];
    }

    public function findById(int $id): ?Publication
    {
        // TODO: Implement findById() method.
    }

    public function save(Publication $publication): void {}

    public function delete(int $id): void {}

    public function update(Publication $publication): void {}
}
