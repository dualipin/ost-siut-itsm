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
        $stmt = $this->pdo->prepare("
     insert into mail_queue (recipient, subject, body, status)
     values (:recipient, :subject, :body, 'pending')
    ");

        $stmt->execute([
            ":recipient" => json_encode($addresses),
            ":subject" => $subject,
            ":body" => $body,
        ]);
    }
}
