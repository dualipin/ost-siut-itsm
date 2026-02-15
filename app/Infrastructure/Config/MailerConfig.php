<?php

namespace App\Infrastructure\Config;

final readonly class MailerConfig
{
    public function __construct(
        public string $host,
        public string $user,
        public string $password,
        public int $port,
        public string $charset,
    ) {
    }
}