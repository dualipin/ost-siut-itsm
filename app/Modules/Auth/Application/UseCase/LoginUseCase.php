<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\DTO\UserSession;
use App\Modules\Auth\Domain\Exception\TooManyAttemptsException;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Service\CredentialVerifier;
use App\Modules\Auth\Domain\Service\LoginAttemptPolicy;
use App\Shared\Context\UserContextInterface;

final readonly class LoginUseCase
{
    public function __construct(
        private CredentialRepositoryInterface $credentialRepository,
        private CredentialVerifier $credentialVerifier,
        private LoginAttemptPolicy $loginAttemptPolicy,
        private UserContextInterface $userContext,
        private AuthEventLogger $authEventLogger,
    ) {}

    /**
     * @throws TooManyAttemptsException
     */
    public function execute(
        string $email,
        string $password,
        string $ipAddress,
        string $userAgent,
    ): bool {
        $this->loginAttemptPolicy->ensureNotLocked($email);

        $credential = $this->credentialRepository->findByEmail($email);

        if (!$this->credentialVerifier->verify($credential, $password)) {
            $this->authEventLogger->failedLoginAttempt(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Intento de inicio de sesión fallido",
            );

            return false;
        }

        $this->authEventLogger->successLoginAttempt(
            email: $email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            userId: $credential->id,
        );

        $this->userContext->set(
            new UserSession(
                id: $credential->id,
                email: $credential->email,
                role: $credential->role,
            ),
        );

        return true;
    }
}
