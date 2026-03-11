<?php

namespace App\Infrastructure\Templating;

interface RendererInterface
{
    public function render(string $template, array $params = []): void;

    public function renderToString(
        string $template,
        array $params = [],
    ): string;
}
