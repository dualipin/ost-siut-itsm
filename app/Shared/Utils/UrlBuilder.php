<?php

namespace App\Shared\Utils;

use App\Infrastructure\Config\AppConfig;

final readonly class UrlBuilder
{
    public function __construct(private AppConfig $config) {}

    public function to(string $path, array $params = []): string
    {
        // Validar si ya es una URL absoluta
        $url = str_contains($path, "://")
            ? $path
            : rtrim($this->config->baseUrl, "/") . "/" . ltrim($path, "/");

        if (empty($params)) {
            return $url;
        }

        // Detectar si ya hay un "?" en la URL para usar "&" en su lugar
        $separator = str_contains($url, "?") ? "&" : "?";
        return $url . $separator . http_build_query($params);
    }
}
