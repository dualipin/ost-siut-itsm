<?php

namespace App\Infrastructure\Templating\Latte;

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Env\EnvironmentInterface;

class ViteHelper
{
    private bool $isDev;
    private string $manifestPath;

    public function __construct(private readonly AppConfig $config)
    {
        // Asumiendo que tu EnvironmentInterface puede decirnos si estamos en local
        $this->isDev = $config->isDev;

        // En Vite 5+, el manifest se guarda dentro de la carpeta .vite/
        $this->manifestPath = __DIR__ . '/../../../../public/build/.vite/manifest.json';
    }

    public function generateTags(string $entry): string
    {
        if ($this->isDev) {
            $tags = '';

            // Construir dev base a partir de `baseUrl`.
            // Si `baseUrl` incluye puerto, se usa; si no, se añade :5173.
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

            // Detectamos si es React verificando la extensión
            $isReact = str_ends_with($entry, '.tsx') || str_ends_with($entry, '.jsx');

            // Si es React, inyectamos el preamble apuntando al dev server configurado
            if ($isReact) {
                $tags .= '<script type="module">'
                    . "import RefreshRuntime from '{$devBase}/@react-refresh';"
                    . 'RefreshRuntime.injectIntoGlobalHook(window);'
                    . 'window.$RefreshReg$ = () => {};'
                    . 'window.$RefreshSig$ = () => (type) => type;'
                    . "window.__vite_plugin_react_preamble_installed__ = true;"
                    . '</script>';
            }

            // Cliente Vite y archivo de entrada desde el dev server configurado
            $tags .= '<script type="module" src="' . $devBase . '/@vite/client"></script>';
            $tags .= '<script type="module" src="' . $devBase . '/' . ltrim($entry, '/') . '"></script>';

            return $tags;
        }
        // ... código de producción ...

        // Si estamos en producción, leemos el manifest para obtener los archivos minificados
        if (!file_exists($this->manifestPath)) {
            return '';
        }

        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        if (!isset($manifest[$entry])) {
            return "";
        }

        $file = $manifest[$entry]['file'];
        $cssFiles = $manifest[$entry]['css'] ?? [];

        // Construir base para assets en producción usando `baseUrl` configurado
        $base = rtrim($this->config->baseUrl ?? '', '/');
        $assetsBase = $base === '' ? '/build' : $base . '/build';

        // Generar script de JS
        $tags = '<script type="module" src="' . $assetsBase . '/' . $file . '"></script>';

        // Generar links de CSS (si tu componente Vue tiene <style>)
        foreach ($cssFiles as $css) {
            $tags .= '<link rel="stylesheet" href="' . $assetsBase . '/' . $css . '">';
        }

        return $tags;
    }
}
