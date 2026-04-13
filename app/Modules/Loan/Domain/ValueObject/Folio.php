<?php

namespace App\Modules\Loan\Domain\ValueObject;

final readonly class Folio
{
    public function __construct(
        private string $prefix,
        private int $year,
        private string $sequence
    ) {
        if (empty($prefix)) {
            throw new \InvalidArgumentException('Folio prefix cannot be empty');
        }
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Folio year must be between 2000 and 2100');
        }
        if (!preg_match('/^\d{3,}$/', $sequence)) {
            throw new \InvalidArgumentException('Folio sequence must be at least 3 digits');
        }
    }

    public function toString(): string
    {
        return "{$this->prefix}-{$this->year}-{$this->sequence}";
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function sequence(): int
    {
        return (int) $this->sequence;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function parse(string $folio): self
    {
        $parts = explode('-', $folio);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid folio format: {$folio}");
        }
        return new self($parts[0], (int) $parts[1], $parts[2]);
    }
}
