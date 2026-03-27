<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Entity;

use DateTimeImmutable;

final readonly class RequestType
{
    public function __construct(
        public int $requestTypeId,
        public string $name,
        public ?string $description,
        public bool $active,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }
}
