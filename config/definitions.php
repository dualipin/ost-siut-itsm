<?php

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Mail\DatabaseMailQueue;
use App\Infrastructure\Mail\EmailService;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Persistence\PdoTransactionManager;
use App\Infrastructure\Persistence\TransactionManager;
use App\Infrastructure\Session\PhpSession;
use App\Infrastructure\Session\SessionInterface;
use App\Infrastructure\Templating\Latte\LatteExtension;
use App\Infrastructure\Templating\Latte\LatteRenderer;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Auth\Infrastructure\Mail\PasswordRecoveryMailer;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;
use App\Shared\Context\UserContext;
use App\Shared\Context\UserContextInterface;
use App\Shared\Context\UserProviderInterface;
use DI\ContainerBuilder;
use Dompdf\Dompdf;
use Latte\Engine;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

use function DI\autowire;
use function DI\get;

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
            $mail->CharSet = 'UTF-8';
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
            $options->setIsRemoteEnabled(false);
            $options->setIsHtml5ParserEnabled(true);
            $options->setIsFontSubsettingEnabled(false);
            $dompdf->setOptions($options);
            return $dompdf;
        },

        LoggerInterface::class => function () {
            $log = new Logger("app");
            $log->pushHandler(
                new StreamHandler(
                    dirname(__DIR__) . "/logs/app.log",
                    Level::Warning,
                ),
            );

            return $log;
        },

        RendererInterface::class => autowire(LatteRenderer::class),
        EmailService::class => autowire(EmailService::class),
        MailerInterface::class => autowire(DatabaseMailQueue::class),
        SessionInterface::class => autowire(PhpSession::class),
        TransactionManager::class => autowire(PdoTransactionManager::class),

        PasswordRecoveryMailer::class => function (
            MailerInterface $mailer,
            RendererInterface $renderer,
        ) {
            return new PasswordRecoveryMailer(
                $mailer,
                $renderer,
                dirname(__DIR__),
            );
        },

        PasswordRecoveryNotifierInterface::class => autowire(PasswordRecoveryMailer::class),

        // shared
        UserContextInterface::class => autowire(UserContext::class),
        UserProviderInterface::class => get(UserContextInterface::class),
    ]);
};
