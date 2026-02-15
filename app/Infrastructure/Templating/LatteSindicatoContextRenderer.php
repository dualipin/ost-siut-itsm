<?php

namespace App\Infrastructure\Templating;

use App\Module\Sindicato\Repository\SindicatoRepository;
use App\Shared\Context\TenantContext;
use App\Shared\View\SindicatoViewContextProvider;

final readonly class LatteSindicatoContextRenderer implements RendererInterface
{
    public function __construct(
        private SindicatoViewContextProvider $provider,
        private RendererInterface $renderer,
    ) {}

    public function render(string $template, array $params = []): void
    {
        $this->renderer->render($template, $this->mergeContext($params));
    }

    public function renderToString(string $template, array $params = []): string
    {
        return $this->renderer->renderToString(
            $template,
            $this->mergeContext($params),
        );
    }

    private function mergeContext(array $params): array
    {
        return [...$params, ...$this->provider->get()];
    }
}
