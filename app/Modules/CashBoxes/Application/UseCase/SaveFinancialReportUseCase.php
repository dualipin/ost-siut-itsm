<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\FinancialReport;
use App\Modules\CashBoxes\Domain\Repository\FinancialReportRepositoryInterface;
use DateTimeImmutable;

final readonly class SaveFinancialReportUseCase
{
    public function __construct(private FinancialReportRepositoryInterface $financialReportRepository)
    {
    }

    /**
     * @param array<string, mixed>|null $summary
     */
    public function execute(?int $boxId, int $generatedBy, string $periodStart, string $periodEnd, string $filePath, ?array $summary): void
    {
        $report = new FinancialReport(
            reportId: 0,
            boxId: $boxId,
            generatedBy: $generatedBy,
            periodStart: new DateTimeImmutable($periodStart),
            periodEnd: new DateTimeImmutable($periodEnd),
            filePath: $filePath,
            summary: $summary,
            createdAt: new DateTimeImmutable(),
        );

        $this->financialReportRepository->save($report);
    }
}
