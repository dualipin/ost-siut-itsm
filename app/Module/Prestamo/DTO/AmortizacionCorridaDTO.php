<?php

namespace App\Module\Prestamo\DTO;

use DateTimeImmutable;

final readonly class AmortizacionCorridaDTO
{
    public function __construct(
        public DateTimeImmutable $fechaPago,
        public string $capitalSoluto,
        public string $interes,
        public string $valor,
        public string $saldo,
    ) {}
}
