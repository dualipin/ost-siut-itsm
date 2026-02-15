<?php

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Config\DatabaseConfig;
use App\Infrastructure\Config\MailerConfig;
use App\Infrastructure\Config\UploadConfig;
use App\Infrastructure\Env\EnvironmentInterface;

return function (\DI\ContainerBuilder $container): void {
    $container->addDefinitions([
        AppConfig::class => fn(EnvironmentInterface $env) => new AppConfig(
            isDev: $env->get("APP_ENV", "prod") === "dev",
            baseUrl: (string) $env->get("BASE_URL", "http://localhost"),
            database: new DatabaseConfig(
                host: (string) $env->get("DB_HOST"),
                database: (string) $env->get("DB_NAME"),
                user: (string) $env->get("DB_USER"),
                password: (string) $env->get("DB_PASSWORD"), // ← unificado
                port: (int) $env->get("DB_PORT", 3306),
                charset: "utf8mb4",
            ),
            mailer: new MailerConfig(
                host: (string) $env->get("MAILER_HOST"), // ← unificado
                user: (string) $env->get("MAILER_USER"), // ← unificado
                password: (string) $env->get("MAILER_PASSWORD"),
                port: (int) $env->get("MAILER_PORT", 465),
                charset: "UTF-8",
            ),
            upload: new UploadConfig(
                publicUrl: "uploads",
                publicDir: dirname(__DIR__) . "/public/uploads",
                privateDir: dirname(__DIR__) . "/uploads",
            ),
        ),
    ]);
};
