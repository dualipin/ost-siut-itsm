<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Exception;

use RuntimeException;

final class ContactMessageNotificationException extends RuntimeException
{
    public static function missingRecipients(): self
    {
        return new self(
            "No hay destinatarios configurados en MAILER_ADMIN_NOTIFICATIONS.",
        );
    }
}
