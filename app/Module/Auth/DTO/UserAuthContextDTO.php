<?php

namespace App\Module\Auth\DTO;

use App\Module\Auth\Enum\RolEnum;

class UserAuthContextDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $apellidos,
        public string $email,
        public RolEnum $rol,
    ) {}

    public function getFullName(): string
    {
        return "$this->nombre $this->apellidos";
    }
}
