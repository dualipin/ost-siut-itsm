<?php

declare(strict_types=1);

namespace App\Modules\Loan\Domain\DTO;

use DateTimeImmutable;

final readonly class LoanSimulationIncomeTypeDTO
{
    public function __construct(
        public int $id,
        public int $amount,
        public ?DateTimeImmutable $lastDiscountDate,
    ) {}
}
