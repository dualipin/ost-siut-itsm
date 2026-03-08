<?php

namespace App\Modules\Auth\Infrastructure\Mail;

use App\Infrastructure\Mail\MailerInterface;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;

use function htmlspecialchars;
use function sprintf;

final readonly class PasswordRecoveryMailer implements
    PasswordRecoveryNotifierInterface
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendMagicLink(string $email, string $magicLink): void
    {
        $safeLink = htmlspecialchars(
            $magicLink,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );

        $subject = "Recuperación de contraseña";
        $body = sprintf(
            '<p>Recibimos una solicitud para recuperar tu contraseña.</p><p><a href="%s">Haz clic aquí para ingresar con tu magic link</a></p><p>Si no solicitaste este acceso, ignora este correo.</p>',
            $safeLink,
        );
        $altBody = "Abre este enlace para recuperar tu contraseña: {$magicLink}";

        $this->mailer->send([$email], $subject, $body, $altBody);
    }
}