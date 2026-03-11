<?php

namespace App\Shared\Utils;

use App\Infrastructure\Config\AppConfig;

use function str_split;
use function strpos;

final readonly class UrlBuilder
{
    public function __construct(private AppConfig $config) {}

    public function to(
        string $path,
        array $params = [],
        bool $replaceOlderParams = true,
    ): string {
        // Validar si ya es una URL absoluta
        $url = str_contains($path, "://")
            ? $path
            : rtrim($this->config->baseUrl, "/") . "/" . ltrim($path, "/");

        if (empty($params)) {
            return $url;
        }

        if ($replaceOlderParams) {
            $url = $this->removeParams($url);
        }

        // Detectar si ya hay un "?" en la URL para usar "&" en su lugar
        $separator = str_contains($url, "?") ? "&" : "?";

        return $url . $separator . http_build_query($params);
    }

    private function removeParams(string $path): string
    {
        $index = strpos($path, "?");

        if (!$index) {
            return $path;
        }

        $realPath = str_split($path, $index);

        return $realPath[0];
    }
}
