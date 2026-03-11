<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Infrastructure\Session\SessionInterface;
use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Shared\Context\UserContextInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private AuthEventLogger $authEventLogger,
        private UserContextInterface $userContext,
        private SessionInterface $session,
    ) {}

    public function execute(
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $user = $this->userContext->get();

        if (!$user) {
            return;
        }

        $this->session->destroy();

        $this->authEventLogger->logoutSuccess(
            userId: $user->id,
            email: $user->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }
}
