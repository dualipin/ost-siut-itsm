<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Shared\Context\UserContextInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private AuthEventLogger $authEventLogger,
        private UserContextInterface $userContext,
    ) {}

    public function execute(
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $ipAddress ??= "";
        $userAgent ??= "";

        $user = $this->userContext->get();

        if (!$user) {
            $this->authEventLogger->logoutFailed(
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
            return;
        }

        $this->authEventLogger->logoutSuccess(
            userId: $user->id,
            email: $user->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        $this->userContext->clear();
    }
}
