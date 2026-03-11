<?php

declare(strict_types=1);

namespace App\Infrastructure\Templating\Latte;

use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserProviderInterface;
use Latte\Engine;

final readonly class LatteRenderer implements RendererInterface
{
    public function __construct(
        private Engine $latte,
        private UserProviderInterface $userProvider,
    ) {}

    public function render(string $template, array $params = []): void
    {
        if (!array_key_exists("authUser", $params)) {
            $params["authUser"] = $this->userProvider->get();
        }

        $this->latte->render($template, $params);
    }

    public function renderToString(string $template, array $params = []): string
    {
        if (!array_key_exists("authUser", $params)) {
            $params["authUser"] = $this->userProvider->get();
        }

        return $this->latte->renderToString($template, $params);
    }
}
