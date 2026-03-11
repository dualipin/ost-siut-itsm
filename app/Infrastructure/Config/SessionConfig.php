<?php

namespace App\Infrastructure\Config;

final readonly class SessionConfig
{
    public function __construct(
        public bool $useStrictMode,
        public bool $cookieSecure,
        public bool $cookieHttpOnly,
        public string $cookieSameSite,
    ) {}
}
