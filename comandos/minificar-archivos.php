<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MatthiasMullie\Minify;

$extensiones = ['css', 'js'];

function minificarDirectorio(string $path, array $extensiones): void
{
    $archivos = scandir($path);

    foreach ($archivos as $archivo) {
        if ($archivo === '.' || $archivo === '..') continue;

        $rutaCompleta = $path . DIRECTORY_SEPARATOR . $archivo;

        if (is_dir($rutaCompleta)) {
            minificarDirectorio($rutaCompleta, $extensiones);
        } else {
            $ext = pathinfo($archivo, PATHINFO_EXTENSION);
            $nombreBase = pathinfo($archivo, PATHINFO_FILENAME);

            // Ignorar si ya es un archivo minificado
            if (!in_array($ext, $extensiones) || str_ends_with($nombreBase, '.min')) continue;

            $nombreMin = $path . DIRECTORY_SEPARATOR . $nombreBase . '.min.' . $ext;

            // Solo minificar si no existe el archivo minificado o si el original cambió
            if (!file_exists($nombreMin) || filemtime($rutaCompleta) > filemtime($nombreMin)) {
                $min = $ext === 'css' ? new Minify\CSS($rutaCompleta) : new Minify\JS($rutaCompleta);
                $min->minify($nombreMin);
                echo "Minificado: $archivo -> " . basename($nombreMin) . PHP_EOL;
            } else {
                echo "Omitido (ya minificado): $archivo" . PHP_EOL;
            }
        }
    }
}

// Ejecutar
minificarDirectorio(__DIR__ . '/../aplicacion/assets', $extensiones);
minificarDirectorio(__DIR__ . '/../assets', $extensiones);


echo "Minificación completada." . PHP_EOL;