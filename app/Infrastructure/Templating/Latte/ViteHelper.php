<?php

namespace App\Infrastructure\Templating\Latte;

use App\Infrastructure\Config\AppConfig;
use RuntimeException;

class ViteHelper
{
    private bool $isDev;
    private string $manifestPath;

    public function __construct(private readonly AppConfig $config)
    {
        $this->isDev = $config->isDev;

        // Idealmente, esta ruta debería venir del AppConfig o de un path global,
        // pero mantenemos tu lógica actual.
        $this->manifestPath = realpath(__DIR__ . '/../../../../public/build/.vite/manifest.json') ?: __DIR__ . '/../../../../public/build/.vite/manifest.json';
    }

    public function generateTags(string $entry): string
    {
        if ($this->isDev) {
            // ... (Tu código de desarrollo se mantiene igual) ...
            $tags = '';

            $base = $this->config->baseUrl;
            $parsed = parse_url($base);
            if ($parsed === false || !isset($parsed['host'])) {
                $devBase = 'http://localhost:5173';
            } else {
                $scheme = $parsed['scheme'] ?? 'http';
                $host = $parsed['host'];
                $port = $parsed['port'] ?? 5173;
                $devBase = $scheme . '://' . $host . ':' . $port;
            }

            $isReact = str_ends_with($entry, '.tsx') || str_ends_with($entry, '.jsx');

            if ($isReact) {
                $tags .= '<script type="module">'
                    . "import RefreshRuntime from '{$devBase}/@react-refresh';"
                    . 'RefreshRuntime.injectIntoGlobalHook(window);'
                    . 'window.$RefreshReg$ = () => {};'
                    . 'window.$RefreshSig$ = () => (type) => type;'
                    . "window.__vite_plugin_react_preamble_installed__ = true;"
                    . '</script>';
            }

            $tags .= '<script type="module" src="' . $devBase . '/@vite/client"></script>';
            $tags .= '<script type="module" src="' . $devBase . '/' . ltrim($entry, '/') . '"></script>';

            return $tags;
        }

        // --- CÓDIGO DE PRODUCCIÓN ---

        // 1. Verificamos si el manifest existe. Si no, lanzamos un error en lugar de ocultarlo.
        if (!file_exists($this->manifestPath)) {
            throw new RuntimeException("Vite manifest no encontrado en: {$this->manifestPath}. Asegúrate de haber ejecutado 'npm run build'.");
        }

        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        // 2. Verificamos si el entry existe en el manifest. Si no, mostramos qué claves existen.
        if (!isset($manifest[$entry])) {
            $availableKeys = implode(', ', array_keys($manifest));
            throw new RuntimeException("El entry '{$entry}' no se encontró en el manifest de Vite. Las claves disponibles son: {$availableKeys}.");
        }

        $file = $manifest[$entry]['file'];
        $cssFiles = $manifest[$entry]['css'] ?? [];

        // Construir base para assets en producción
        $base = rtrim($this->config->baseUrl ?? '', '/');
        $assetsBase = $base === '' ? '/build' : $base . '/build';

        // Generar script de JS
        $tags = '<script type="module" src="' . rtrim($assetsBase, '/') . '/' . ltrim($file, '/') . '"></script>';

        // Generar links de CSS
        foreach ($cssFiles as $css) {
            $tags .= '<link rel="stylesheet" href="' . rtrim($assetsBase, '/') . '/' . ltrim($css, '/') . '">';
        }

        return $tags;
    }
}