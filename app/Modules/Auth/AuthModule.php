<?php

namespace App\Modules\Auth;

use App\Modules\AbstractModule;
use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Application\UseCase\ChangePasswordWithTokenUseCase;
use App\Modules\Auth\Application\UseCase\LoginUseCase;
use App\Modules\Auth\Application\UseCase\LogoutUseCase;
use App\Modules\Auth\Application\UseCase\PasswordResetUseCase;
use App\Modules\Auth\Application\UseCase\RecoverPasswordWithMagicLinkUseCase;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\CredentialVerifier;
use App\Modules\Auth\Domain\Service\LoginAttemptPolicy;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;
use App\Modules\Auth\Infrastructure\Persistence\PdoAuthLogRepository;
use App\Modules\Auth\Infrastructure\Persistence\PdoCredentialRepository;
use App\Modules\Auth\Infrastructure\Persistence\PdoPasswordRecoveryRepository;

final class AuthModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        AuthLogRepositoryInterface::class => PdoAuthLogRepository::class,
        CredentialRepositoryInterface::class => PdoCredentialRepository::class,
        PasswordRecoveryInterface::class => PdoPasswordRecoveryRepository::class,
    ];

    protected const array SERVICES = [
        AuthEventLogger::class,
        CredentialVerifier::class,
        LoginAttemptPolicy::class,
        MagicLinkTokenPolicy::class,
    ];

    protected const array USE_CASES = [
        ChangePasswordWithTokenUseCase::class,
        LoginUseCase::class,
        LogoutUseCase::class,
        PasswordResetUseCase::class,
        RecoverPasswordWithMagicLinkUseCase::class,
    ];
}
