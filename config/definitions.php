<?php

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\LatteExtensions;
use App\Infrastructure\Templating\LatteRenderer;
use App\Infrastructure\Templating\RendererInterface;
use Latte\Engine;

use PHPMailer\PHPMailer\PHPMailer;

use function DI\autowire;

return function (\DI\ContainerBuilder $container) {
    $container->addDefinitions([
        Engine::class => function (
            AppConfig $config,
            LatteExtensions $extensions,
        ) {
            $cacheDir = __DIR__ . "/../tmp/latte_cache";

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            $latte = new Engine();

            $latte->addExtension($extensions);

            $latte->setTempDirectory($cacheDir);
            $latte->setAutoRefresh($config->isDev);

            return $latte;
        },

        PDO::class => function (AppConfig $config) {
            $dsn = "mysql:host={$config->database->host};dbname={$config->database->database};charset=utf8mb4";
            return new PDO($dsn, $config->database->user, $config->database->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        },

        PHPMailer::class => function (AppConfig $config) {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config->mailer->host;
            $mail->SMTPAuth = true;
            $mail->Username = $config->mailer->user;
            $mail->Password = $config->mailer->password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config->mailer->port;
            return $mail;
        },

        RendererInterface::class => autowire(LatteRenderer::class),
    ]);
};
