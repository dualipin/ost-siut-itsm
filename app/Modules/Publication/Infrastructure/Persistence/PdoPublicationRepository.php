<?php

namespace App\Modules\Publication\Infrastructure\Persistence;

use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;

final readonly class PdoPublicationRepository implements
    PublicationRepositoryInterface {}
