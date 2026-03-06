<?php

namespace App\Infrastructure\Mail;

interface MailerInterface
{
    /**
     * @param string[] $addresses
     */
    public function send(
        array $addresses,
        string $subject,
        string $body,
        string $altBody = "",
    ): void;
}
