<?php

namespace App\Module\Mensajeria\DTO;

use App\Module\Mensajeria\Enum\PrioridadMensajeEnum;
use App\Module\Mensajeria\Enum\TipoMensajeEnum;

final readonly class CrearMensajeExternoDTO
{
    public function __construct(
        public string $asunto,
        public string $nombreCompleto,
        public string $correo,
        public string $telefono,
        public string $mensaje,
        public TipoMensajeEnum $tipo = TipoMensajeEnum::ContactoGeneral,
        public PrioridadMensajeEnum $prioridad = PrioridadMensajeEnum::Media,
    ) {}
}
