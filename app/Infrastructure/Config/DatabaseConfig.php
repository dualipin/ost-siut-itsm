<?php

namespace App\Infrastructure\Config;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public string $database,
        public string $user,
        public string $password,
        public int $port,
        public string $charset,
    ) {
    }
}