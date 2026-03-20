<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Service;

/**
 * Interface QuestionNotifierInterface
 *
 * Contrato para notificar a los administradores sobre nuevas dudas de transparencia.
 */
interface QuestionNotifierInterface
{
    public function notifyAdminOfNewQuestion(
        int $threadId,
        string $name,
        string $email,
        string $question,
    ): void;
}
