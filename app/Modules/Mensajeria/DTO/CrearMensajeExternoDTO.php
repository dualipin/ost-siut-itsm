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
    ) {
        $this->validateRequired('asunto', $asunto);
        $this->validateRequired('nombreCompleto', $nombreCompleto);
        $this->validateRequired('mensaje', $mensaje);

        $this->validateEmail($correo);
        $this->validateLength('asunto', $asunto, 5, 200);
        $this->validateLength('mensaje', $mensaje, 10, 2000);
        $this->validateLength('nombreCompleto', $nombreCompleto, 3, 100);
    }

    private function validateRequired(string $field, string $value): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException("El campo '$field' es requerido");
        }
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("El correo electrónico no es válido");
        }
    }

    private function validateLength(string $field, string $value, int $min, int $max): void
    {
        $length = mb_strlen(trim($value));
        if ($length < $min || $length > $max) {
            throw new \InvalidArgumentException(
                "El campo '$field' debe tener entre $min y $max caracteres"
            );
        }
    }
}
