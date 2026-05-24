<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Repository\PdoBaseRepository;
use App\Modules\CashBoxes\Domain\Entity\AnnualFinancialReport;
use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;
use PDO;

final class PdoAnnualFinancialReportRepository extends PdoBaseRepository implements AnnualFinancialReportRepositoryInterface
{
    /**
     * @return AnnualFinancialReport[]
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, year, document FROM financial_reports_annual ORDER BY year DESC, id DESC');

        if ($stmt === false) {
            return [];
        }

        return array_map(
            fn (array $row): AnnualFinancialReport => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findById(int $id): ?AnnualFinancialReport
    {
        $stmt = $this->pdo->prepare('SELECT id, year, document FROM financial_reports_annual WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByYear(int $year): ?AnnualFinancialReport
    {
        $stmt = $this->pdo->prepare('SELECT id, year, document FROM financial_reports_annual WHERE year = :year LIMIT 1');
        $stmt->execute(['year' => $year]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(AnnualFinancialReport $report): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO financial_reports_annual (year, document) VALUES (:year, :document)');
        $stmt->execute([
            'year' => $report->year,
            'document' => $report->document,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(AnnualFinancialReport $report): void
    {
        $stmt = $this->pdo->prepare('UPDATE financial_reports_annual SET year = :year, document = :document WHERE id = :id');
        $stmt->execute([
            'id' => $report->id,
            'year' => $report->year,
            'document' => $report->document,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM financial_reports_annual WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array{id: scalar, year: scalar, document: scalar} $row
     */
    private function hydrate(array $row): AnnualFinancialReport
    {
        return new AnnualFinancialReport(
            id: (int) $row['id'],
            year: (int) $row['year'],
            document: (string) $row['document'],
        );
    }
}