<?php

namespace App\Modules\Publication\Domain\Repository;

use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;

interface PublicationRepositoryInterface
{
    /**
     * @return Publication[]
     */
    public function findByType(PublicationTypeEnum $type): array;
    public function findById(int $id): ?Publication;
}
