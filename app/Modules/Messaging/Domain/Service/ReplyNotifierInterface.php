<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Service;

interface ReplyNotifierInterface
{
    public function notifyContactReply(
        string $toEmail,
        string $toName,
        string $subject,
        string $replyBody,
    ): void;

    public function notifyQuestionReply(
        string $toEmail,
        string $toName,
        string $question,
        string $replyBody,
    ): void;
}
