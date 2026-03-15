<?php

namespace App\Modules\Publication;

use App\Modules\AbstractModule;
use App\Modules\ModuleInterface;
use App\Modules\Publication\Application\UseCase\CreatePublicationUseCase;
use App\Modules\Publication\Application\UseCase\DeletePublicationUseCase;
use App\Modules\Publication\Application\UseCase\FindPublicationByIdUseCase;
use App\Modules\Publication\Application\UseCase\FindPublicationsByTypeUseCase;
use App\Modules\Publication\Application\UseCase\UpdatePublicationUseCase;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;
use App\Modules\Publication\Infrastructure\Persistence\PdoPublicationRepository;
use App\Modules\Publication\Infrastructure\Upload\PublicationAttachmentUploader;

final class PublicationModule extends AbstractModule implements ModuleInterface
{
    protected const array REPOSITORIES = [
        PublicationRepositoryInterface::class =>
            PdoPublicationRepository::class,
    ];

    protected const array SERVICES = [
        PublicationAttachmentUploader::class,
    ];

    protected const array USE_CASES = [
        CreatePublicationUseCase::class,
        UpdatePublicationUseCase::class,
        DeletePublicationUseCase::class,
        FindPublicationByIdUseCase::class,
        FindPublicationsByTypeUseCase::class,
    ];
}
