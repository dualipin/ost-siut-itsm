<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Exception;

use DomainException;

final class InsufficientFundsException extends DomainException
{
    public function __construct(string $message = "Insufficient funds in the cash box.")
    {
        parent::__construct($message);
    }
}
