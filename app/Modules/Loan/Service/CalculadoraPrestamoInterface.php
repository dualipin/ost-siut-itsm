<?php

namespace App\Module\Prestamo\Service;

use App\Module\Prestamo\DTO\AmortizacionCorridaDTO;

interface CalculadoraPrestamoInterface
{
    /**
     * @return AmortizacionCorridaDTO[]|AmortizacionCorridaDTO
     */
    public function generarCorrida(): array|AmortizacionCorridaDTO;
}
