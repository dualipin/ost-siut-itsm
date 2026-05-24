<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\AnnualFinancialReport;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportValidationException;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportYearAlreadyExistsException;
use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;

final readonly class UpdateAnnualFinancialReportUseCase
{
    public function __construct(
        private AnnualFinancialReportRepositoryInterface $repository,
    ) {
    }

    public function execute(int $id, int $year, string $document): AnnualFinancialReport
    {
        if ($id <= 0) {
            throw new AnnualFinancialReportValidationException('El identificador del informe es obligatorio.');
        }

        if ($year <= 0) {
            throw new AnnualFinancialReportValidationException('El año del informe es obligatorio.');
        }

        if (trim($document) === '') {
            throw new AnnualFinancialReportValidationException('Debes indicar el archivo del informe.');
        }

        $report = $this->repository->findById($id);

        if ($report === null) {
            throw new AnnualFinancialReportNotFoundException($id);
        }

        $reportByYear = $this->repository->findByYear($year);
        if ($reportByYear !== null && $reportByYear->id !== $id) {
            throw new AnnualFinancialReportYearAlreadyExistsException($year);
        }

        $updatedReport = new AnnualFinancialReport(
            id: $id,
            year: $year,
            document: $document,
        );

        $this->repository->update($updatedReport);

        return $updatedReport;
    }
}