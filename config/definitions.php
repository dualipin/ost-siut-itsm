<?php

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Mail\DatabaseMailQueue;
use App\Infrastructure\Mail\EmailService;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\LatteExtension;
use App\Infrastructure\Templating\LatteRenderer;
use App\Infrastructure\Templating\RendererInterface;
use DI\ContainerBuilder;
use Dompdf\Dompdf;
use Latte\Engine;
use PHPMailer\PHPMailer\PHPMailer;

use PHPMailer\PHPMailer\SMTP;

use function DI\autowire;

return function (ContainerBuilder $container) {
    $container->addDefinitions([
        Engine::class => function (
            AppConfig $config,
            LatteExtension $extensions,
        ) {
            $cacheDir = __DIR__ . "/../tmp/latte_cache";

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            $latte = new Engine();

            $latte->addExtension($extensions);

            $latte->setCacheDirectory($cacheDir);
            $latte->setAutoRefresh($config->isDev);

            return $latte;
        },

        PDO::class => function (AppConfig $config) {
            $dsn = "mysql:host={$config->database->host};dbname={$config->database->database};charset=utf8mb4";
            return new PDO(
                $dsn,
                $config->database->user,
                $config->database->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            );
        },

        PHPMailer::class => function (AppConfig $config) {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config->mailer->host;
            $mail->SMTPAuth = true;
            $mail->Username = $config->mailer->user;
            $mail->Password = $config->mailer->password;

            // Puerto 465 = SSL (SMTPS), otros puertos = TLS (STARTTLS)
            if ($config->mailer->port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $config->mailer->port;
            $mail->Timeout = 30; // Timeout explícito para SMTP
            $mail->SMTPKeepAlive = false; // Reconectar para cada email

            return $mail;
        },

        Dompdf::class => function () {
            $dompdf = new Dompdf();
            $dompdf->setPaper("Letter");
            $options = $dompdf->getOptions();
            $options->setIsRemoteEnabled(true);
            $dompdf->setOptions($options);
            return $dompdf;
        },

        RendererInterface::class => autowire(LatteRenderer::class),
        EmailService::class => autowire(EmailService::class),
        MailerInterface::class => autowire(DatabaseMailQueue::class),
    ]);
};
