<?php

namespace App\Module\Prestamo\DTO;

use DateTimeImmutable;

final readonly class AmortizacionCorridaDTO
{
    public function __construct(
        public DateTimeImmutable $fechaPago,
        public float $capitalSoluto,
        public float $interes,
        public float $valor,
        public float $saldo,
    ) {}
}
