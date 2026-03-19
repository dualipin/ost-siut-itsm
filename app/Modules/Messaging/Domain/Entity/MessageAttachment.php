<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Entity;

use DateTimeImmutable;

final readonly class MessageAttachment
{
    public function __construct(
        public ?int $id,
        public int $messageId,
        public string $filePath,
        public ?string $fileName = null,
        public ?string $mimeType = null,
        public ?int $fileSize = null,
        public ?DateTimeImmutable $uploadedAt = null,
    ) {}
}
