<?php

namespace App\Modules\User\Domain\ValueObject;

use DateTimeImmutable;

final readonly class WorkData
{
    public function __construct(
        public ?string $category = null,
        public ?string $department = null,
        public ?string $nss = null,
        public float $salary = 0.0,
        public ?DateTimeImmutable $workStartDate = null,
    ) {}
}
