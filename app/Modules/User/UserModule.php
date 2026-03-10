<?php

namespace App\Modules\User;

use App\Modules\AbstractModule;
use App\Modules\User\Application\UseCase\CreateUserUseCase;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Modules\User\Infrastructure\Persistence\PdoUserRepository;

final class UserModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        UserRepositoryInterface::class => PdoUserRepository::class,
    ];

    protected const array USE_CASES = [CreateUserUseCase::class];
}
