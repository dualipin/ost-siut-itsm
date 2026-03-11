<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;

final readonly class RecoverPasswordWithMagicLinkUseCase
{
    private const int MagicLinkTtlMinutes = 15;

    public function __construct(
        private PasswordRecoveryInterface $passwordRecoveryRepository,
        private CredentialRepositoryInterface $credentialRepository,
        private MagicLinkTokenPolicy $magicLinkTokenPolicy,
        private UserContextInterface $userContext,
        private AuthEventLogger $authEventLogger,
    ) {}

    public function execute(
        string $token,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): bool {
        if (!$this->magicLinkTokenPolicy->isValid($token)) {
            return false;
        }

        $email = $this->passwordRecoveryRepository->findEmailByValidToken(
            token: $token,
            ttlMinutes: self::MagicLinkTtlMinutes,
        );

        if (!$email) {
            $this->authEventLogger->passwordResetFailed(
                email: "",
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Token inválido o expirado",
            );

            return false;
        }

        $credential = $this->credentialRepository->findByEmail($email);

        if (!$credential) {
            $this->passwordRecoveryRepository->consumeToken($token);

            $this->authEventLogger->passwordResetFailed(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "Usuario no encontrado",
            );

            return false;
        }

        $this->passwordRecoveryRepository->consumeToken($token);

        $this->userContext->set(
            new AuthenticatedUser(
                id: $credential->id,
                name: $credential->name,
                email: $credential->email,
                role: $credential->role,
            ),
        );

        $this->authEventLogger->passwordResetSuccess(
            userId: $credential->id,
            email: $credential->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return true;
    }
}