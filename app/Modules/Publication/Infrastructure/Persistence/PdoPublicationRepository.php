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
    }

    public function findById(int $id): ?Publication
    {
        // TODO: Implement findById() method.
    }
}
