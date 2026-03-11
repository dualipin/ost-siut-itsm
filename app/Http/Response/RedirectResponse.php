<?php

namespace App\Http\Response;

final readonly class RedirectResponse
{
    public function __construct(private string $location) {}

    public function send(): void
    {
        header("Location: {$this->location}", true, 302);
        exit();
    }
}
