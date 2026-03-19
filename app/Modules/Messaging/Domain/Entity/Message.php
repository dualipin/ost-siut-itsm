<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Entity;

use DateTimeImmutable;

final readonly class Message
{
    public function __construct(
        public ?int $id,
        public int $threadId,
        public string $body,
        public ?int $senderId = null,
        public ?DateTimeImmutable $sentAt = null,
        public ?DateTimeImmutable $readAt = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {}

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isSystemMessage(): bool
    {
        return $this->senderId === null;
    }
}
