<?php

namespace App;

use App\Infrastructure\Env\Environment;
use App\Infrastructure\Env\EnvironmentInterface;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

class Bootstrap
{
    public static function buildContainer(): ContainerInterface
    {
        return self::build();
    }

    private static function build(): ContainerInterface
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();

        // Claves unificadas con settings.php
        $dotenv->required([
            "DB_HOST",
            "DB_NAME",
            "DB_USER",
            "DB_PASSWORD", // ← era DB_PASS
            "MAILER_HOST", // ← era MAIL_HOST
            "MAILER_USER", // ← era MAIL_USER
            "MAILER_PASSWORD",
        ]);

        // Usar EnvironmentInterface en lugar de $_ENV directo
        $env = new Environment();
        $isDev = $env->get("APP_ENV", "prod") === "dev";

        $builder = new ContainerBuilder();

        // Registrar Environment como implementación de la interfaz
        $builder->addDefinitions([
            EnvironmentInterface::class => $env,
        ]);

        if (!$isDev) {
            $builder->enableCompilation(dirname(__DIR__) . "/tmp/di_cache");
            $builder->writeProxiesToFile(
                true,
                dirname(__DIR__) . "/tmp/di_proxies",
            );
        }

        // Cargar configuraciones
        foreach (
            ["settings", "definitions", "services", "repositories"]
            as $config
        ) {
            $loader = require dirname(__DIR__) . "/config/{$config}.php";
            $loader($builder);
        }

        return $builder->build();
    }
}
