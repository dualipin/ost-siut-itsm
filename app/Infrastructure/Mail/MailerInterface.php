<?php

namespace App\Infrastructure\Mail;

interface MailerInterface
{
    /**
     * @param string[] $addresses
     * @param string $subject
     * @param string $body
     * @param string $altBody
     * @return void
     */
    public function send(
        array $addresses,
        string $subject,
        string $body,
        string $altBody = "",
    ): void;
}
