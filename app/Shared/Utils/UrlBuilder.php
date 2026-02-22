<?php

namespace App\Shared\Utils;

use App\Infrastructure\Config\AppConfig;

final readonly class UrlBuilder
{
    public function __construct(private AppConfig $config) {}

    /**
     * Construye una URL absoluta a una ruta interna.
     * Ej: ->to('/cuentas/reset-password.php', ['token' => $uuid])
     */
    public function to(string $path, array $params = []): string
    {
        $base = rtrim($this->config->baseUrl, "/") . "/" . ltrim($path, "/");
        return empty($params) ? $base : $base . "?" . http_build_query($params);
    }
}
