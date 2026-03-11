<?php

namespace App\Infrastructure\Mail;

use PDO;

final readonly class DatabaseMailQueue implements MailerInterface
{
    public function __construct(private PDO $pdo) {}

    public function send(
        array $addresses,
        string $subject,
        string $body,
        string $altBody = "",
    ): void {
        $validAddresses = array_values(
            array_filter(
                $addresses,
                fn($address) =>
                    is_string($address) &&
                    filter_var($address, FILTER_VALIDATE_EMAIL),
            ),
        );

        if ($validAddresses === []) {
            throw new \InvalidArgumentException("No hay destinatarios válidos para encolar");
        }

        $stmt = $this->pdo->prepare(
            "insert into mail_queue (recipient, subject, body, alt_body, status) values (:recipient, :subject, :body, :alt_body, 'pending')",
        );

        foreach ($validAddresses as $address) {
            $stmt->execute([
                ":recipient" => $address,
                ":subject" => $subject,
                ":body" => $body,
                ":alt_body" => $altBody,
            ]);
        }
    }
}
