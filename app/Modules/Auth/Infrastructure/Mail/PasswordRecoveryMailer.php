<?php

namespace App\Modules\Auth\Infrastructure\Mail;

use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;

final readonly class PasswordRecoveryMailer implements
    PasswordRecoveryNotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private RendererInterface $renderer,
        private string $templateBasePath,
    ) {}

    public function sendMagicLink(string $email, string $magicLink): void
    {
        $subject = "Recuperación de contraseña";
        $templatePath = $this->templateBasePath . "/templates/emails/reset-password.latte";
        $body = $this->renderer->renderToString(
            $templatePath,
            [
                "link" => $magicLink,
                "year" => date('Y'),
            ],
        );
        $altBody = "Abre este enlace para recuperar tu contraseña: {$magicLink}";

        $this->mailer->send([$email], $subject, $body, $altBody);
    }
}