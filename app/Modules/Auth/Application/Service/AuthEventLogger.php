<?php

namespace App\Modules\Auth\Application\Service;

use App\Modules\Auth\Domain\Entity\AuthLog;
use App\Modules\Auth\Domain\Enum\AuthLogActionEnum;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;

final readonly class AuthEventLogger
{
    public function __construct(
        private AuthLogRepositoryInterface $authLogRepository,
    ) {}

    public function successLoginAttempt(
        string $email,
        string $ipAddress,
        string $userAgent,
        int $userId,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::LoginAttempt,
                success: true,
                userId: $userId,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );

        $this->authLogRepository->updateLastLogin($userId);
    }

    public function failedLoginAttempt(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $errorMessage = null,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::LoginAttempt,
                success: false,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $errorMessage,
            ),
        );
    }

    public function logoutSuccess(
        int $userId,
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::Logout,
                success: true,
                userId: $userId,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );
    }

    public function logoutFailed(?string $ipAddress, ?string $userAgent): void
    {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::Logout,
                success: false,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: "intento fallido de cierre de sesión",
            ),
        );
    }

    public function passwordResetRequested(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::PasswordReset,
                success: true,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );
    }

    public function passwordResetSuccess(
        int $userId,
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::PasswordReset,
                success: true,
                userId: $userId,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );

        $this->authLogRepository->updateLastLogin($userId);
    }

    public function passwordResetFailed(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
        string $errorMessage,
    ): void {
        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::PasswordReset,
                success: false,
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                errorMessage: $errorMessage,
            ),
        );
    }
}
