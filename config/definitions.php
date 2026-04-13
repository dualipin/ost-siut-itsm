<?php

use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Mail\DatabaseMailQueue;
use App\Infrastructure\Mail\EmailService;
use App\Infrastructure\Mail\MailQueueProcessor;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Mail\PdoMailQueueRepository;
use App\Infrastructure\Persistence\PdoTransactionManager;
use App\Infrastructure\Persistence\TransactionManager;
use App\Infrastructure\Session\PhpSession;
use App\Infrastructure\Session\SessionInterface;
use App\Infrastructure\Templating\Latte\LatteExtension;
use App\Infrastructure\Templating\Latte\LatteRenderer;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Loan\Application\Service\PdfGeneratorInterface;
use App\Modules\Loan\Infrastructure\Service\DompdfLoanPdfGenerator;
use App\Modules\Auth\Infrastructure\Mail\PasswordRecoveryMailer;
use App\Modules\Auth\Domain\Service\PasswordRecoveryNotifierInterface;
use App\Modules\Dashboard\Application\Service\AlertEvaluationService;
use App\Modules\Dashboard\Application\UseCase\GetAdministradorDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetAgremiadoDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetFinanzasDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetLiderDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetPublicDashboardDataUseCase;
use App\Modules\Dashboard\Infrastructure\Persistence\AdminFailedMailQueueAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\AdminPendingDocumentsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\AdminUnattendedMessagesAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\AgremiadoPendingSignaturesAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\FinanzasOverduePaymentsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\FinanzasPendingDocumentsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\LiderInactiveRegistrationsAlertEvaluator;
use App\Modules\Dashboard\Infrastructure\Persistence\LiderOverdueLoansAlertEvaluator;
use App\Modules\Messaging\Domain\Service\ContactMessageNotifierInterface;
use App\Modules\Messaging\Infrastructure\Mail\ContactMessageMailer;
use App\Modules\Messaging\Domain\Service\QuestionNotifierInterface;
use App\Modules\Messaging\Infrastructure\Mail\PhpMailerQuestionNotifier;
use App\Modules\Messaging\Domain\Service\ReplyNotifierInterface;
use App\Modules\Messaging\Infrastructure\Mail\ReplyMailer;
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
use Psr\Container\ContainerInterface;

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
        PdfGeneratorInterface::class => function (
            AppConfig $config,
            RendererInterface $renderer,
        ) {
            return new DompdfLoanPdfGenerator(
                $config,
                $renderer,
                dirname(__DIR__),
            );
        },
        EmailService::class => autowire(EmailService::class),
        PdoMailQueueRepository::class => autowire(PdoMailQueueRepository::class),
        MailQueueProcessor::class => autowire(MailQueueProcessor::class),
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

        // Use the same factory-defined instance when the notifier interface is requested
        PasswordRecoveryNotifierInterface::class => get(PasswordRecoveryMailer::class),

        ContactMessageMailer::class => function (
            MailerInterface $mailer,
            RendererInterface $renderer,
            AppConfig $config,
        ) {
            return new ContactMessageMailer(
                $mailer,
                $renderer,
                $config->mailer->adminNotifications,
                dirname(__DIR__),
            );
        },

        ContactMessageNotifierInterface::class => get(ContactMessageMailer::class),

        PhpMailerQuestionNotifier::class => function (
            MailerInterface $mailer,
            RendererInterface $renderer,
            AppConfig $config,
        ) {
            return new PhpMailerQuestionNotifier(
                $mailer,
                $renderer,
                $config->mailer->adminNotifications,
                dirname(__DIR__),
            );
        },

        QuestionNotifierInterface::class => get(PhpMailerQuestionNotifier::class),

        ReplyMailer::class => function (
            MailerInterface $mailer,
            RendererInterface $renderer,
        ) {
            return new ReplyMailer(
                $mailer,
                $renderer,
                dirname(__DIR__),
            );
        },

        ReplyNotifierInterface::class => get(ReplyMailer::class),

        // Dashboard module - AlertEvaluationService factory
        AlertEvaluationService::class => function (ContainerInterface $container) {
            $evaluators = [
                $container->get(LiderOverdueLoansAlertEvaluator::class),
                $container->get(LiderInactiveRegistrationsAlertEvaluator::class),
                $container->get(AdminPendingDocumentsAlertEvaluator::class),
                $container->get(AdminUnattendedMessagesAlertEvaluator::class),
                $container->get(AdminFailedMailQueueAlertEvaluator::class),
                $container->get(FinanzasOverduePaymentsAlertEvaluator::class),
                $container->get(FinanzasPendingDocumentsAlertEvaluator::class),
                $container->get(AgremiadoPendingSignaturesAlertEvaluator::class),
            ];
            return new AlertEvaluationService($evaluators);
        },

        // shared
        UserContextInterface::class => autowire(UserContext::class),
        UserProviderInterface::class => get(UserContextInterface::class),
    ]);
};


