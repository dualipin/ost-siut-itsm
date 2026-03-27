<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Entity;

use DateTimeImmutable;

final readonly class RequestAttachment
{
    public function __construct(
        public int $attachmentId,
        public int $requestId,
        public string $filePath,
        public ?string $mimeType,
        public ?string $description,
        public DateTimeImmutable $uploadedAt,
    ) {
    }

    public function fileName(): string
    {
        return basename($this->filePath);
    }
}
