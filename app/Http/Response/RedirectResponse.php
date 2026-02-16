<?php

namespace App\Http\Response;

use App\Infrastructure\Config\AppConfig;

final readonly class RedirectResponse
{
    private string $location;

    public function __construct(
        AppConfig $config, // Inyectamos la config
        string $path,      // Ej: "/mi-archivo.php"
        array $params = []
    ) {
        // Normalizamos la URL
        $fullPath = self::normalizePath($config->baseUrl, $path);
        $this->location = self::buildUrl($fullPath, $params);
    }

    private static function normalizePath(string $baseUrl, string $path): string
    {
        // Si el path ya es una URL absoluta (http...), no tocamos nada
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Limpiamos slashes duplicados y unimos base + path
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function send(): void
    {
        header("Location: {$this->location}", true, 302);
        exit();
    }

    private static function buildUrl(string $location, array $params): string
    {
        if (empty($params)) {
            return $location;
        }

        $parsed = parse_url($location);
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query = [...$query, ...$params];

        // Reconstrucción inteligente
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $newQuery = '?' . http_build_query($query);
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return "$scheme$host$port$path$newQuery$fragment";
    }
}