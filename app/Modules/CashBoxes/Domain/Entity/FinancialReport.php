<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use DateTimeImmutable;

final readonly class FinancialReport
{
    /**
     * @param array<string, mixed>|null $summary
     */
    public function __construct(
        public int $reportId,
        public ?int $boxId,
        public int $generatedBy,
        public DateTimeImmutable $periodStart,
        public DateTimeImmutable $periodEnd,
        public string $filePath,
        public ?array $summary,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
