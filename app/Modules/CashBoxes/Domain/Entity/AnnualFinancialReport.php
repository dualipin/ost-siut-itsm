<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

final readonly class AnnualFinancialReport
{
    public function __construct(
        public int $id,
        public int $year,
        public string $document,
    ) {
    }

    public function fileName(): string
    {
        return basename(str_replace('\\', '/', $this->document));
    }
}