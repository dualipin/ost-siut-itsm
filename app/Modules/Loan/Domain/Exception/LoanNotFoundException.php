<?php

namespace App\Modules\Loan\Domain\Exception;

use Exception;

final class LoanNotFoundException extends Exception
{
    public static function withId(int $loanId): self
    {
        return new self("Loan with ID {$loanId} not found");
    }

    public static function withFolio(string $folio): self
    {
        return new self("Loan with folio {$folio} not found");
    }
}
