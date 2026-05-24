<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Exception;

use RuntimeException;

final class AnnualFinancialReportYearAlreadyExistsException extends RuntimeException
{
    public function __construct(int $year)
    {
        parent::__construct(sprintf('Ya existe un informe financiero registrado para el año %d.', $year));
    }
}