<?php

namespace App\Infrastructure\Mail;

use App\Infrastructure\Config\AppConfig;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Psr\Log\LoggerInterface;

final readonly class EmailService implements MailerInterface
{
    public function __construct(
        private PHPMailer $phpMailer,
        private AppConfig $config,
        private LoggerInterface $logger,
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

        try {
            $this->phpMailer->setFrom(
                $this->config->mailer->fromAddress,
                $this->config->mailer->fromName,
            );

            $validAddresses = array_filter(
                $addresses,
                fn($address) => filter_var($address, FILTER_VALIDATE_EMAIL),
            );

            if (empty($validAddresses)) {
                throw new \InvalidArgumentException(
                    "No hay destinatarios válidos",
                );
            }

            foreach ($validAddresses as $address) {
                $this->phpMailer->addAddress($address);
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
            $this->logger->error("Error al enviar correo: " . $e->getMessage());
            throw new \RuntimeException(
                "Error al enviar correo: " . $e->getMessage(),
            );
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning(
                "Intento de envío sin destinatarios válidos",
            );
            throw $e; // relanzas tal cual, es un error del llamador
        }
    }
}
