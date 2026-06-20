<?php

declare(strict_types=1);

namespace App\Modules\Loan\Domain\DTO;

final readonly class LoanSimulationDTO
{
    /**
     * @param LoanSimulationDiscountDTO[] $descuentos
     */
    public function __construct(
        public float $montoPrestamo,
        public string $fechaOtorgamiento,
        public int $mesesPagar,
        public int $diasAdicionales,
        public float $tasaInteres,
        public array $descuentos,
    ) {}
}


