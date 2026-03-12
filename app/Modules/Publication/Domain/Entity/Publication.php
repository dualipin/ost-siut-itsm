<?php

namespace App\Modules\Publication\Domain\Entity;

use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use DateTimeImmutable;

final readonly class Publication
{
    /**
     * @param PublicationAttachment[] $attachments
     */
    public function __construct(
        public int $id,
        public int $authorId,
        public string $title,
        public string $content,
        public PublicationTypeEnum $type,
        public array $attachments = [],
        public ?string $thumbnailUrl = null,
        public ?string $summary = null,
        public ?DateTimeImmutable $expirationDate = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function isExpired(): bool
    {
        if ($this->expirationDate === null) {
            return false;
        }
        return $this->expirationDate < new DateTimeImmutable();
    }

    public function hasAttachments(): bool
    {
        return count($this->attachments) > 0;
    }
}
