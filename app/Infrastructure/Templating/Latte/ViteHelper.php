<?php

namespace App\Infrastructure\Templating\Latte;

use App\Infrastructure\Env\EnvironmentInterface;

class ViteHelper
{
    private bool $isDev;
    private string $manifestPath;

    public function __construct(EnvironmentInterface $env)
    {
        // Asumiendo que tu EnvironmentInterface puede decirnos si estamos en local
        $this->isDev = $env->get('APP_ENV') === 'dev';

        // En Vite 5+, el manifest se guarda dentro de la carpeta .vite/
        $this->manifestPath = __DIR__ . '/../../../../public/build/.vite/manifest.json';
    }

    public function generateTags(string $entry): string
    {
  if ($this->isDev) {
            $tags = '';

            // 1. Detectamos si es React verificando la extensión
            $isReact = str_ends_with($entry, '.tsx') || str_ends_with($entry, '.jsx');

            // 2. Solo si es React, inyectamos el Preamble usando Nowdoc
            if ($isReact) {
                $tags .= <<<'HTML'
                    <script type="module">
                        import RefreshRuntime from 'http://localhost:5173/@react-refresh'
                        RefreshRuntime.injectIntoGlobalHook(window)
                        window.$RefreshReg$ = () => {}
                        window.$RefreshSig$ = () => (type) => type
                        window.__vite_plugin_react_preamble_installed__ = true
                    </script>
                HTML;
            }

            // 3. Inyectamos el cliente de Vite y tu archivo usando Nowdoc
            $baseHtml = <<<'HTML'
                <script type="module" src="http://localhost:5173/@vite/client"></script>
                <script type="module" src="http://localhost:5173/{{ENTRY_FILE}}"></script>
            HTML;

            $tags .= str_replace('{{ENTRY_FILE}}', $entry, $baseHtml);

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

        // Generar script de JS
        $tags = '<script type="module" src="/build/' . $file . '"></script>';

        // Generar links de CSS (si tu componente Vue tiene <style>)
        foreach ($cssFiles as $css) {
            $tags .= '<link rel="stylesheet" href="/build/' . $css . '">';
        }

        return $tags;
    }
}
