<?php

namespace App\Modules\Publication;

use App\Modules\AbstractModule;
use App\Modules\ModuleInterface;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;
use App\Modules\Publication\Infrastructure\Persistence\PdoPublicationRepository;

final class PublicationModule extends AbstractModule implements ModuleInterface
{
    protected const array REPOSITORIES = [
        PublicationRepositoryInterface::class =>
            PdoPublicationRepository::class,
    ];
}
