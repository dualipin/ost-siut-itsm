<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Entity;

use App\Modules\Transparency\Domain\Enum\TransparencyType;
use DateTimeImmutable;

final readonly class Transparency
{
    public function __construct(
        public ?int $id,
        public int $authorId,
        public string $title,
        public ?string $summary,
        public TransparencyType $type,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $datePublished,
        public bool $isPrivate
    ) {
    }
}
