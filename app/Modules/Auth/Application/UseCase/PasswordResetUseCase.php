<?php

namespace App\Modules\Auth\Application\UseCase;

use App\Modules\Auth\Application\Service\AuthEventLogger;
use App\Modules\Auth\Domain\Repository\CredentialRepositoryInterface;
use App\Modules\Auth\Domain\Repository\PasswordRecoveryInterface;
use App\Modules\Auth\Domain\Service\MagicLinkTokenPolicy;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;
use App\Shared\Utils\UrlBuilder;

final readonly class PasswordResetUseCase
{
    public function __construct(
        private CredentialRepositoryInterface $credentialRepository,
        private PasswordRecoveryInterface $passwordRecoveryRepository,
        private PasswordRecoveryNotifierInterface $passwordRecoveryNotifier,
        private MagicLinkTokenPolicy $magicLinkTokenPolicy,
        private UrlBuilder $urlBuilder,
        private AuthEventLogger $authEventLogger,
    ) {}

    public function execute(
        string $email,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $credential = $this->credentialRepository->findByEmail($email);

        if (!$credential) {
            return;
        }

        $token = $this->magicLinkTokenPolicy->generate();

        $this->passwordRecoveryRepository->storeMagicLink($email, $token);

        $magicLink = $this->urlBuilder->to("/cuentas/recuperar-contra.php", [
            "token" => $token,
        ]);

        $this->passwordRecoveryNotifier->sendMagicLink($email, $magicLink);

        $this->authEventLogger->passwordResetRequested(
            email: $email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }
}
