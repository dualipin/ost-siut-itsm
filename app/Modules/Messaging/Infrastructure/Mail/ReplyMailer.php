<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Mail;

use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Domain\Service\ReplyNotifierInterface;
use Throwable;

use function date;
use function implode;

final readonly class ReplyMailer implements ReplyNotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private RendererInterface $renderer,
        private string $basePath,
    ) {}

    public function notifyContactReply(
        string $toEmail,
        string $toName,
        string $subject,
        string $replyBody,
    ): void {
        $subjectLine = "Respuesta a su mensaje: {$subject}";
        $templatePath = "{$this->basePath}/templates/emails/mensajeria/respuesta-contacto.latte";

        $body = $this->renderer->renderToString($templatePath, [
            'name' => $toName,
            'subject' => $subject,
            'replyBody' => $replyBody,
            'year' => date('Y'),
        ]);

        $altBody = implode("\n", [
            "Respuesta a su mensaje de contacto",
            "-----------------------------------",
            "Hola {$toName},",
            "",
            "Le respondemos sobre: {$subject}",
            "",
            $replyBody,
            "",
            "Atentamente,",
            "Equipo SIUT ITSM",
        ]);

        try {
            $this->mailer->send([$toEmail], $subjectLine, $body, $altBody);
        } catch (Throwable) {
            // Silenciamos para no bloquear el use case.
            // En producción se registraría en el log.
        }
    }

    public function notifyQuestionReply(
        string $toEmail,
        string $toName,
        string $question,
        string $replyBody,
    ): void {
        $subjectLine = "Respuesta a su consulta de transparencia";
        $templatePath = "{$this->basePath}/templates/emails/mensajeria/respuesta-duda.latte";

        $body = $this->renderer->renderToString($templatePath, [
            'name' => $toName,
            'question' => $question,
            'replyBody' => $replyBody,
            'year' => date('Y'),
        ]);

        $altBody = implode("\n", [
            "Respuesta a su consulta de transparencia",
            "-----------------------------------------",
            "Hola {$toName},",
            "",
            "Su consulta:",
            $question,
            "",
            "Nuestra respuesta:",
            $replyBody,
            "",
            "Atentamente,",
            "Equipo SIUT ITSM",
        ]);

        try {
            $this->mailer->send([$toEmail], $subjectLine, $body, $altBody);
        } catch (Throwable) {
            // Silenciamos para no bloquear el use case.
        }
    }
}
