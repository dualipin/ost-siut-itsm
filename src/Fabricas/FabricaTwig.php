<?php

namespace App\Fabricas;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FabricaTwig
{
    private static Environment $twig;

    public static function instance(
            string $template,
            string $templatesFolder = __DIR__ . '/../../twig-templates'
    ): Environment {
        if (self::$twig === null) {
            $loader = new FilesystemLoader($templatesFolder);
            self::$twig = new Environment($loader, [
                    'cache' => ($_ENV['APP_ENV'] ?? 'prod') === 'dev',
            ]);
            return self::$twig;
        }

        return self::$twig;
    }
}