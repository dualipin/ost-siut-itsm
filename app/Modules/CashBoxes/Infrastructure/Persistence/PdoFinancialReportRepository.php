<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Infrastructure\Persistence;

use App\Modules\CashBoxes\Domain\Entity\FinancialReport;
use App\Modules\CashBoxes\Domain\Repository\FinancialReportRepositoryInterface;
use DateTimeImmutable;
use PDO;

final readonly class PdoFinancialReportRepository implements FinancialReportRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(FinancialReport $report): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO financial_reports (
                box_id,
                generated_by,
                period_start,
                period_end,
                file_path,
                summary_json,
                created_at
            ) VALUES (
                :box_id,
                :generated_by,
                :period_start,
                :period_end,
                :file_path,
                :summary_json,
                :created_at
            )
        ');

        $stmt->execute([
            'box_id' => $report->boxId,
            'generated_by' => $report->generatedBy,
            'period_start' => $report->periodStart->format('Y-m-d'),
            'period_end' => $report->periodEnd->format('Y-m-d'),
            'file_path' => $report->filePath,
            'summary_json' => $report->summary !== null ? json_encode($report->summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => $report->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findRecent(?int $boxId = null, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $where = '';
        $params = [];

        if ($boxId !== null) {
            $where = 'WHERE box_id = :box_id';
            $params['box_id'] = $boxId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM financial_reports ' . $where . ' ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute($params);

        return array_map(
            fn (array $row): FinancialReport => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): FinancialReport
    {
        $summary = null;
        if (is_string($row['summary_json']) && $row['summary_json'] !== '') {
            $decoded = json_decode($row['summary_json'], true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }

        return new FinancialReport(
            reportId: (int) $row['report_id'],
            boxId: isset($row['box_id']) ? (int) $row['box_id'] : null,
            generatedBy: (int) $row['generated_by'],
            periodStart: new DateTimeImmutable((string) $row['period_start']),
            periodEnd: new DateTimeImmutable((string) $row['period_end']),
            filePath: (string) $row['file_path'],
            summary: $summary,
            createdAt: new DateTimeImmutable((string) $row['created_at'])
        );
    }
}
