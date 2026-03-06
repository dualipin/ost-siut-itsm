<?php

namespace App;

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Env\Environment;
use App\Infrastructure\Env\EnvironmentInterface;
use App\Modules\Auth\AuthModule;
use App\Modules\LaborUnion\LaborUnionModule;
use App\Modules\Loan\LoanModule;
use App\Modules\Messaging\MessagingModule;
use App\Modules\Publication\PublicationModule;
use App\Modules\User\UserModule;
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

        $modules = [
            new AuthModule(),
            new LaborUnionModule(),
            new LoanModule(),
            new MessagingModule(),
            new PublicationModule(),
            new UserModule(),
        ];

        foreach ($modules as $module) {
            $module->register($builder);
        }

        // Cargar configuraciones
        foreach (
            ["settings", "definitions", "services", "repositories"]
            as $config
        ) {
            $loader = require dirname(__DIR__) . "/config/{$config}.php";
            $loader($builder);
        }

        $container = $builder->build();

        $appConfig = $container->get(AppConfig::class);

        $session = $appConfig->session;

        ini_set("session.use_strict_mode", $session->useStrictMode ? "1" : "0");
        ini_set("session.cookie_secure", $session->cookieSecure ? "1" : "0");
        ini_set(
            "session.cookie_httponly",
            $session->cookieHttpOnly ? "1" : "0",
        );
        ini_set("session.cookie_samesite", $session->cookieSameSite);
        ini_set("session.use_only_cookies", "1");

        return $container;
    }
}
