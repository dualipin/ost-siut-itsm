<?php

namespace App\Module\Prestamo\Entity;

final readonly class CategoriaTipoIngreso
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $descripcion = null,
        public bool $esPeriodico = false,
        public ?int $frecuenciaDias = null,
        public ?int $mesPagoTentativo = null,
        public ?int $diaPagoTentativo = null,
        public bool $activo = true,
    ) {}
}
