<?php

namespace App\Modules\Loan\Domain\Entity;

final readonly class IncomeType
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public bool $isPeriodic,
        public bool $isActive,
        public ?int $frequencyDays,
        public ?int $paymentMonth,
        public ?int $paymentDay,
    ) {
    }
}