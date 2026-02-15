<?php

namespace App\Infrastructure\Templating;

use App\Infrastructure\Config\AppConfig;
use Latte\Extension;

class LatteExtensions extends Extension
{
    public function __construct(private readonly AppConfig $settings) {}

    public function getFunctions(): array
    {
        return [
            "resolveUrl" => $this->resolveUrl(...),
            "resolveUploadUrl" => $this->resolveUploadUrl(...),
        ];
    }

    public function resolveUrl(string $url): string
    {
        return rtrim($this->settings->baseUrl, "/") . "/" . ltrim($url, "/");
    }

    public function resolveUploadUrl(?string $path): string
    {
        if (!$path) {
            return "";
        }
        $upload = $this->settings->upload;
        return $this->resolveUrl("{$upload->publicUrl}/{$path}");
    }
}
