<?php

namespace App\Modules\Loan\Domain\ValueObject;

final readonly class Money
{
    public function __construct(private float $amount)
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative');
        }
    }

    public function amount(): float
    {
        return round($this->amount, 2);
    }

    public function isZero(): bool
    {
        return $this->amount === 0.0;
    }

    public function isNegativeOrZero(): bool
    {
        return $this->amount <= 0.0;
    }

    public function isGreaterThan(Money $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function add(Money $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function subtract(Money $other): self
    {
        $result = $this->amount - $other->amount;
        if ($result < 0) {
            throw new \InvalidArgumentException('Result cannot be negative');
        }
        return new self($result);
    }

    public function multiply(float $multiplier): self
    {
        return new self($this->amount * $multiplier);
    }

    public function divide(float $divisor): self
    {
        if ($divisor === 0.0) {
            throw new \InvalidArgumentException('Cannot divide by zero');
        }
        return new self($this->amount / $divisor);
    }

    public function equals(Money $other): bool
    {
        return abs($this->amount - $other->amount) < 0.01;
    }

    public function format(): string
    {
        return '$' . number_format($this->amount, 2, ',', '.');
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function fromFloat(float $amount): self
    {
        return new self($amount);
    }
}
