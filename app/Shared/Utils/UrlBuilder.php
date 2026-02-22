<?php

namespace App\Shared\Utils;

use App\Infrastructure\Config\AppConfig;

final readonly class UrlBuilder
{
    public function __construct(private AppConfig $config) {}

    public function to(string $path, array $params = []): string
    {
        $url = filter_var($path, FILTER_VALIDATE_URL)
            ? $path
            : rtrim($this->config->baseUrl, "/") . "/" . ltrim($path, "/");

        return empty($params) ? $url : $url . "?" . http_build_query($params);
    }

    public function away(string $absoluteUrl, array $params = []): string
    {
        return empty($params)
            ? $absoluteUrl
            : $absoluteUrl . "?" . http_build_query($params);
    }
}
