<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;
use DateTimeImmutable;

final readonly class CashBox
{
    public function __construct(
        public int $boxId,
        public int $createdBy,
        public string $name,
        public ?string $description,
        public string $currency,
        public float $initialBalance,
        public float $currentBalance,
        public BoxStatusEnum $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->status === BoxStatusEnum::OPEN;
    }
}
