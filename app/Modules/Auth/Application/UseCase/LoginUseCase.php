<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Exception\TooManyAttemptsException;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Service\CredentialVerifier;
use App\Modules\Auth\Domain\Service\LoginAttemptPolicy;
use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;

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
        try {
            $this->loginAttemptPolicy->ensureNotLocked($email);
        } catch (TooManyAttemptsException $exception) {
            $this->authEventLogger->failedLoginAttempt(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }

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
            new AuthenticatedUser(
                id: $credential->id,
                name: $credential->name,
                email: $credential->email,
                role: $credential->role,
            ),
        );

        return true;
    }
}
