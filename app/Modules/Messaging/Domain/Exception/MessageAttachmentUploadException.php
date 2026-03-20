<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Exception;

use Exception;

final class MessageAttachmentUploadException extends Exception
{
    public static function uploadFailed(string $message): self
    {
        return new self($message);
    }
}
