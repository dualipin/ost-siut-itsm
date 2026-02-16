<?php

namespace App\Http\Response;

use App\Infrastructure\Config\AppConfig;

final readonly class Redirector
{
    public function __construct(
        private AppConfig $config
    ) {}

    public function to(string $path, array $params = []): RedirectResponse
    {
        return new RedirectResponse($this->config, $path, $params);
    }
}