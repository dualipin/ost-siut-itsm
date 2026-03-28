<?php

declare(strict_types=1);

namespace App\Modules\Requests;

use App\Modules\AbstractModule;
use App\Modules\Requests\Application\UseCase\ChangeRequestStatusUseCase;
use App\Modules\Requests\Application\UseCase\CreateRequestTypeUseCase;
use App\Modules\Requests\Application\UseCase\CreateRequestUseCase;
use App\Modules\Requests\Application\UseCase\GetAllRequestsUseCase;
use App\Modules\Requests\Application\UseCase\GetMyRequestsUseCase;
use App\Modules\Requests\Application\UseCase\GetRequestDetailUseCase;
use App\Modules\Requests\Application\UseCase\GetRequestTypesUseCase;
use App\Modules\Requests\Application\UseCase\UpdateRequestTypeUseCase;
use App\Modules\Requests\Domain\Repository\RequestAttachmentRepositoryInterface;
use App\Modules\Requests\Domain\Repository\RequestRepositoryInterface;
use App\Modules\Requests\Domain\Repository\RequestTypeRepositoryInterface;
use App\Modules\Requests\Infrastructure\Persistence\PdoRequestAttachmentRepository;
use App\Modules\Requests\Infrastructure\Persistence\PdoRequestRepository;
use App\Modules\Requests\Infrastructure\Persistence\PdoRequestTypeRepository;

final class RequestsModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        RequestRepositoryInterface::class           => PdoRequestRepository::class,
        RequestTypeRepositoryInterface::class       => PdoRequestTypeRepository::class,
        RequestAttachmentRepositoryInterface::class => PdoRequestAttachmentRepository::class,
    ];

    protected const array USE_CASES = [
        ChangeRequestStatusUseCase::class,
        CreateRequestTypeUseCase::class,
        CreateRequestUseCase::class,
        GetAllRequestsUseCase::class,
        GetMyRequestsUseCase::class,
        GetRequestDetailUseCase::class,
        GetRequestTypesUseCase::class,
        UpdateRequestTypeUseCase::class,
    ];
}
