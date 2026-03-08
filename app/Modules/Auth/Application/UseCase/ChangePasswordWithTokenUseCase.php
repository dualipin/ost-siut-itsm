<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Shared\Context\UserContextInterface;
use App\Shared\Security\AuthenticatedUser;
use PDO;

final readonly class ChangePasswordWithTokenUseCase
{
    private const int MagicLinkTtlMinutes = 15;

    public function __construct(
        private PasswordRecoveryInterface $passwordRecoveryRepository,
        private CredentialRepositoryInterface $credentialRepository,
        private MagicLinkTokenPolicy $magicLinkTokenPolicy,
        private UserContextInterface $userContext,
        private AuthEventLogger $authEventLogger,
        private PDO $pdo,
    ) {}

    public function execute(
        string $token,
        string $newPassword,
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

        // Hash the new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and consume token atomically
        $this->pdo->beginTransaction();
        try {
            $this->credentialRepository->updatePassword($credential->id, $passwordHash);
            $this->passwordRecoveryRepository->consumeToken($token);
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // Authenticate the user
        $this->userContext->set(
            new AuthenticatedUser(
                id: $credential->id,
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

    public function validateToken(string $token): ?string
    {
        if (!$this->magicLinkTokenPolicy->isValid($token)) {
            return null;
        }

        return $this->passwordRecoveryRepository->findEmailByValidToken(
            token: $token,
            ttlMinutes: self::MagicLinkTtlMinutes,
        );
    }
}
