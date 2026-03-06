<?php

namespace App\Infrastructure\Mail;

use App\Infrastructure\Config\AppConfig;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final readonly class EmailService implements MailerInterface
{
    public function __construct(
        private PHPMailer $phpMailer,
        private AppConfig $config,
    ) {}

    public function send(
        array $addresses,
        string $subject,
        string $body,
        string $altBody = "",
    ): void {
        // IMPORTANTE: Limpiar destinatarios previos de la instancia compartida
        $this->phpMailer->clearAllRecipients();
        $this->phpMailer->clearAttachments();

        $this->phpMailer->setFrom(
            $this->config->mailer->fromAddress,
            $this->config->mailer->fromName,
        );

        try {
            foreach ($addresses as $address) {
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $this->phpMailer->addAddress($address);
                }
            }

            $this->phpMailer->isHTML(true);
            $this->phpMailer->Subject = $subject;
            $this->phpMailer->Body = $body;
            $this->phpMailer->AltBody = $altBody;

            if (!$this->phpMailer->send()) {
                throw new \RuntimeException(
                    "Error SMTP: " . $this->phpMailer->ErrorInfo,
                );
            }
        } catch (PHPMailerException $e) {
            throw new \RuntimeException(
                "Error al enviar correo: " . $e->getMessage(),
            );
        }
    }
}
