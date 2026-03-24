<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use App\Modules\CashBoxes\Domain\Enum\TransactionTypeEnum;
use DateTimeImmutable;

final readonly class TransactionCategory
{
    public function __construct(
        public int $categoryId,
        public string $name,
        public TransactionTypeEnum $type,
        public ?string $description,
        public bool $active,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {
    }
}
