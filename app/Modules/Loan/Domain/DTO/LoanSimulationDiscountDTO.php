<?php

declare(strict_types=1);

namespace App\Modules\Loan\Domain\DTO;

final readonly class LoanSimulationDiscountDTO
{
    public function __construct(
        public float $monto,
        public int $tipoId,
        public int $cantidad,
        public ?string $fechaPago = null,
    ) {}
}
