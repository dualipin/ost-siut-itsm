<?php

namespace App\Modules\Loan\Domain\Exception;

use Exception;

final class InvalidPaymentException extends Exception
{
    public static function amountExceedsBalance(float $paymentAmount, float $balance): self
    {
        return new self(
            "Payment amount ({$paymentAmount}) exceeds outstanding balance ({$balance})"
        );
    }

    public static function invalidAmount(): self
    {
        return new self("Payment amount must be greater than zero");
    }
}
