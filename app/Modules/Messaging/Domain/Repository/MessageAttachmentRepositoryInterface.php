<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Repository;

use App\Modules\Messaging\Domain\Entity\MessageAttachment;

interface MessageAttachmentRepositoryInterface
{
    public function create(MessageAttachment $attachment): int;

    /** @return MessageAttachment[] */
    public function findByMessageId(int $messageId): array;
}
