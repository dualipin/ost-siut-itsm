<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Service;

interface ContactMessageNotifierInterface
{
    public function notifyAdminOfNewContact(
        int $threadId,
        string $name,
        string $email,
        ?string $phone,
        string $subject,
        string $message,
    ): void;
}
