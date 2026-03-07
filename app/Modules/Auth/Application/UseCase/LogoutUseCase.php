<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Infrastructure\Session\SessionInterface;
use App\Modules\Auth\Domain\Entity\AuthLog;
use App\Modules\Auth\Domain\Enum\AuthLogActionEnum;
use App\Modules\Auth\Domain\Repository\AuthLogRepositoryInterface;
use App\Shared\Context\UserContextInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private SessionInterface $session,
        private AuthLogRepositoryInterface $authLogRepository,
        private UserContextInterface $userContext,
    ) {}

    public function execute(
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        if (!$this->userContext->isAuthenticated()) {
            $this->authLogRepository->saveAuthLog(
                new AuthLog(
                    action: AuthLogActionEnum::Logout,
                    success: false,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    errorMessage: "Intento de logout sin usuario autenticado",
                ),
            );
            return;
        }

        $user = $this->userContext->get();

        $this->authLogRepository->saveAuthLog(
            new AuthLog(
                action: AuthLogActionEnum::Logout,
                success: true,
                userId: $user->id,
                email: $user->email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ),
        );
        $this->session->destroy();
    }
}
