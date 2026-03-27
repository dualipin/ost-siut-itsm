<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Entity;

use DateTimeImmutable;

final readonly class RequestStatusChange
{
    public function __construct(
        public int $historyId,
        public int $requestId,
        public ?int $changedBy,
        public ?string $statusFrom,
        public string $statusTo,
        public ?string $notes,
        public DateTimeImmutable $changedAt,
    ) {
    }
}
