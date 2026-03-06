<?php

namespace App\Module\Usuario\DTO;

use App\Module\Auth\Enum\RoleEnum;

final readonly class UsuarioSimpleDTO
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $apellidos,
        public string $email,
        public RoleEnum $rol,
        public bool $activo,
        public ?string $departamento = null,
    ) {}

    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }
}
