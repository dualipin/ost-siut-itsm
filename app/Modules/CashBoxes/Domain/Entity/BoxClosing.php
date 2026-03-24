<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use DateTimeImmutable;

final readonly class BoxClosing
{
    public function __construct(
        public int $closingId,
        public int $boxId,
        public int $closedBy,
        public DateTimeImmutable $periodStart,
        public DateTimeImmutable $periodEnd,
        public float $expectedBalance,
        public float $actualBalance,
        public float $difference,
        public float $totalIncome,
        public float $totalExpense,
        public ?string $notes,
        public DateTimeImmutable $closedAt,
    ) {
    }
}
