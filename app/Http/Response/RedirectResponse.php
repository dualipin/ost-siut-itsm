<?php

namespace App\Http\Response;

use App\Infrastructure\Config\AppConfig;

final readonly class RedirectResponse
{
    public function __construct(private string $location) {}

    public function send(): void
    {
        header("Location: {$this->location}", true, 302);
        exit();
    }

    public function getLocation(): string
    {
        return $this->location;
    }
}
