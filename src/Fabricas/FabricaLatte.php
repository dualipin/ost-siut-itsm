<?php
// src/Servicios/FabricaLatte.php
declare(strict_types=1);

namespace App\Fabricas;

use Latte\Engine;

final class FabricaLatte
{
    private static ?Engine $instancia = null;

    // Retorna la instancia de Latte, creando si no existe
    public static function obtenerInstancia(): Engine
    {
        if (self::$instancia === null) {
            $directorioCache = __DIR__ . '/../../temp/latte';
            if (!is_dir($directorioCache)) {
                mkdir($directorioCache, 0777, true);
            }

            $latte = new Engine();
            $latte->setTempDirectory($directorioCache);
            $latte->setAutoRefresh(($_ENV['APP_ENV'] ?? 'prod') === 'dev');

            self::$instancia = $latte;
        }

        return self::$instancia;
    }
}
