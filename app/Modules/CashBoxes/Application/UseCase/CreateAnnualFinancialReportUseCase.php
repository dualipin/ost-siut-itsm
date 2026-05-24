<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Application\UseCase;

use App\Modules\CashBoxes\Domain\Entity\AnnualFinancialReport;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportValidationException;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportYearAlreadyExistsException;
use App\Modules\CashBoxes\Domain\Repository\AnnualFinancialReportRepositoryInterface;

final readonly class CreateAnnualFinancialReportUseCase
{
    public function __construct(
        private AnnualFinancialReportRepositoryInterface $repository,
    ) {
    }

    public function execute(int $year, string $document): AnnualFinancialReport
    {
        if ($year <= 0) {
            throw new AnnualFinancialReportValidationException('El año del informe es obligatorio.');
        }

        if (trim($document) === '') {
            throw new AnnualFinancialReportValidationException('Debes subir el archivo del informe.');
        }

        if ($this->repository->findByYear($year) !== null) {
            throw new AnnualFinancialReportYearAlreadyExistsException($year);
        }

        $report = new AnnualFinancialReport(
            id: 0,
            year: $year,
            document: $document,
        );

        $reportId = $this->repository->save($report);

        return new AnnualFinancialReport(
            id: $reportId,
            year: $year,
            document: $document,
        );
    }
}