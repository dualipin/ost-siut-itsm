<?php

namespace App\Infrastructure\Config;

final readonly class UploadConfig
{
    public function __construct(
        public string $publicUrl,
        public string $publicDir,
        public string $privateDir,
    ) {
    }
}