<?php

namespace App\Http\Response;

use App\Shared\Utils\UrlBuilder;

final readonly class Redirector
{
    public function __construct(private UrlBuilder $urlBuilder) {}

    public function to(string $path, array $params = []): RedirectResponse
    {
        return new RedirectResponse($this->urlBuilder->to($path, $params));
    }
}
