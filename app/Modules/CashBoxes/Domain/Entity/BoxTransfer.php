<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use DateTimeImmutable;

final readonly class BoxTransfer
{
    public function __construct(
        public int $transferId,
        public int $sourceBoxId,
        public int $destinationBoxId,
        public int $createdBy,
        public float $amount,
        public float $sourceBalanceBefore,
        public float $sourceBalanceAfter,
        public float $destinationBalanceBefore,
        public float $destinationBalanceAfter,
        public ?string $notes,
        public DateTimeImmutable $transferredAt,
    ) {
    }
}
