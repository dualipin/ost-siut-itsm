<?php

namespace App\Module\Auth\Service;

use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Auth\Repository\AuthRepository;
use App\Module\Usuario\Repository\UsuarioRepository;
use App\Shared\Utils\UrlBuilder;
use Ramsey\Uuid\Uuid;

final readonly class PasswordResetService
{
    public function __construct(
        private UsuarioRepository $userRepo,
        private AuthRepository $authRepo,
        private RendererInterface $renderer,
        private MailerInterface $mailer,
        private UrlBuilder $urlBuilder,
    ) {}
    public function sendResetLink(string $email): void
    {
        $user = $this->userRepo->findAuthByEmail($email);
        if (!$user) {
            return;
        }

        $uuid = Uuid::uuid7();
        $token = $uuid->getBytes();
        $this->authRepo->saveResetToken($email, $token);

        $link = $this->buildResetLink($uuid->toString());

        $template = $this->renderer->renderToString(
            __DIR__ . "/../../../../templates/emails/reset-password.latte",
            [
                "link" => $link,
            ],
        );

        $this->mailer->send(
            [$email],
            "Restablecer contraseña",
            $template,
            "Copia y pega el siguiente enlace en tu navegador para restablecer tu contraseña: $link",
        );
    }

    private function buildResetLink(string $token): string
    {
        return $this->urlBuilder->to("/cuentas/reset-password.php", [
            "token" => $token,
        ]);
    }
}
