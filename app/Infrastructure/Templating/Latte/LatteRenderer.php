<?php

declare(strict_types=1);

namespace App\Infrastructure\Templating;

use Latte\Engine;

final readonly class LatteRenderer implements RendererInterface
{
    public function __construct(private Engine $latte) {}

    public function render(string $template, array $params = []): void
    {
        $this->latte->render($template, $params);
    }

    public function renderToString(string $template, array $params = []): string
    {
        return $this->latte->renderToString($template, $params);
    }
}
