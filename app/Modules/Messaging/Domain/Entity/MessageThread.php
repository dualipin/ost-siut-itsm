<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Entity;

use App\Modules\Messaging\Domain\Enum\ExternalChannel;
use App\Modules\Messaging\Domain\Enum\ThreadStatus;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Modules\Messaging\Domain\Enum\ThreadVisibility;
use DateTimeImmutable;

final readonly class MessageThread
{
    public function __construct(
        public ?int $id,
        public ThreadType $threadType,
        public ThreadStatus $status,
        public ThreadVisibility $visibility,
        public ?int $senderId = null,
        public ?string $externalName = null,
        public ?string $externalEmail = null,
        public ?string $externalPhone = null,
        public ?int $recipientId = null,
        public ?string $subject = null,
        public ?int $assignedTo = null,
        public ?ExternalChannel $externalChannel = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {}

    public function isExternal(): bool
    {
        return $this->senderId === null
            && $this->externalName !== null;
    }
}
