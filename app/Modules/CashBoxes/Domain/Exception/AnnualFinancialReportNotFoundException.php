<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Exception;

use RuntimeException;

final class AnnualFinancialReportNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('No se encontro el informe financiero anual con id %d.', $id));
    }
}