<?php

namespace App\Modules\Publication\Domain\Entity;

use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use DateTimeImmutable;

final readonly class Publication
{
    public function __construct(
        public string $title,
        public string $content,
        public array $images,
        public DateTimeImmutable $publicationDate,
        public PublicationTypeEnum $type,
        public ?array $attachments = null,
        public ?DateTimeImmutable $expirationDate = null,
        public ?string $summary = null,
        public ?int $id = null,
    ) {}
}
