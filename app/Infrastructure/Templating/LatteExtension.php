<?php

namespace App\Infrastructure\Templating;

use App\Infrastructure\Config\AppConfig;
use App\Shared\Utils\UrlBuilder;
use Latte\Extension;

class LatteExtension extends Extension
{
    public function __construct(
        private readonly UrlBuilder $urlBuilder,
        private readonly AppConfig $settings,
    ) {}

    public function getFunctions(): array
    {
        return [
            "url" => $this->urlBuilder->to(...),
            "upload" => $this->resolveUploadUrl(...),
        ];
    }

    public function resolveUploadUrl(?string $path): string
    {
        if (!$path) {
            return $this->urlBuilder->to("/assets/images/logo.webp");
        }

        return $this->urlBuilder->to(
            "{$this->settings->upload->publicUrl}/{$path}",
        );
    }
}
