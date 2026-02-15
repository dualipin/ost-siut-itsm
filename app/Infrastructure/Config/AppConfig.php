<?php

namespace App\Infrastructure\Config;

final readonly class AppConfig
{
    public function __construct(
        public bool $isDev,
        public string $baseUrl,
        public DatabaseConfig $database,
        public MailerConfig $mailer,
        public UploadConfig $upload,
    ) {
    }
}