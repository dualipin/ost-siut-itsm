<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\DTO;

use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;

final readonly class CashBoxFilterCriteria
{
    public function __construct(
        public ?string $name = null,
        public ?BoxStatusEnum $status = null,
        public ?float $minInitialBalance = null,
        public ?float $maxInitialBalance = null,
        public ?float $minCurrentBalance = null,
        public ?float $maxCurrentBalance = null,
        public string $sortBy = 'created_at',
        public string $sortOrder = 'DESC',
    ) {
    }
}
