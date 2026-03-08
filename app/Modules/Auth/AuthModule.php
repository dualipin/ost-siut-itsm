<?php

namespace App\Modules\Auth;

use App\Modules\AbstractModule;
use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Application\UseCase\LoginUseCase;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Service\CredentialVerifier;
use App\Modules\Auth\Domain\Service\LoginAttemptPolicy;
use App\Modules\Auth\Infrastructure\Persistence\PdoAuthLogRepository;
use App\Modules\Auth\Infrastructure\Persistence\PdoCredentialRepository;

final class AuthModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        AuthLogRepositoryInterface::class => PdoAuthLogRepository::class,
        CredentialRepositoryInterface::class => PdoCredentialRepository::class,
    ];

    protected const array SERVICES = [
        AuthEventLogger::class,
        CredentialVerifier::class,
        LoginAttemptPolicy::class,
    ];

    protected const array USE_CASES = [LoginUseCase::class];
}
