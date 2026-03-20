<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Mail;

use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Domain\Service\QuestionNotifierInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function count;
use function date;
use function filter_var;
use function implode;
use function is_array;
use function preg_split;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const PREG_SPLIT_NO_EMPTY;

final readonly class PhpMailerQuestionNotifier implements QuestionNotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private RendererInterface $renderer,
        private string $adminNotifications,
        private string $basePath,
    ) {}

    public function notifyAdminOfNewQuestion(
        int $threadId,
        string $name,
        string $email,
        string $question,
    ): void {
        $recipients = $this->extractRecipients($this->adminNotifications);

        if ($recipients === []) {
            return; // No hay a quien notificar
        }

        $subject = "Nueva duda de transparencia #{$threadId}: {$name}";
        $templatePath = "{$this->basePath}/templates/emails/mensajeria/pregunta-transparencia.latte";

        $body = $this->renderer->renderToString($templatePath, [
            'threadId' => $threadId,
            'name' => $name,
            'email' => $email,
            'question' => $question,
            'year' => date('Y'),
        ]);

        $altBody = $this->buildAltBody($threadId, $name, $email, $question);

        try {
            $this->mailer->send($recipients, $subject, $body, $altBody);
        } catch (Throwable) {
            // Silenciosamente fallamos la notificación para no bloquear el Use Case,
            // pero en un entorno real aquí registraríamos en log.
        }
    }

    /**
     * @return string[]
     */
    private function extractRecipients(string $rawRecipients): array
    {
        $pieces = preg_split('/[\s,;]+/', trim($rawRecipients), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($pieces) || count($pieces) === 0) {
            return [];
        }

        $normalized = array_map(static fn(string $value): string => trim($value), $pieces);
        $validEmails = array_filter(
            $normalized,
            static fn(string $value): bool => (bool) filter_var($value, FILTER_VALIDATE_EMAIL),
        );

        return array_values(array_unique($validEmails));
    }

    private function buildAltBody(int $threadId, string $name, string $email, string $question): string
    {
        return implode("\n", [
            "Nueva duda de transparencia",
            "---------------------------",
            "Hilo: #{$threadId}",
            "Remitente: {$name}",
            "Correo: {$email}",
            "",
            "Consulta:",
            $question,
            "",
            "Atentamente,",
            "Sistema SIUTITSM",
        ]);
    }
}
