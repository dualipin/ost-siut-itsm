<?php

namespace App\Modules\Loan\Domain\ValueObject;

final readonly class InterestRate
{
    public function __construct(private float $rate)
    {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('Interest rate must be between 0 and 100');
        }
    }

    public function annual(): float
    {
        return $this->rate;
    }

    public function asDecimal(): float
    {
        return $this->rate / 100.0;
    }

    public function fortnightly(): float
    {
        return $this->rate / 24.0;
    }

    public function daily(): float
    {
        return $this->rate / 365.0;
    }

    public function format(): string
    {
        return number_format($this->rate, 2) . '%';
    }

    public static function fromPercentage(float $percentage): self
    {
        return new self($percentage);
    }
}
