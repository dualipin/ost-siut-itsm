<?php

namespace App\Module\Auth\DTO;

use App\Module\Auth\Enum\RoleEnum;

class UserAuthContextDTO
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $apellidos,
        public string $email,
        public RoleEnum $rol,
    ) {}

    public function getFullName(): string
    {
        return "$this->nombre $this->apellidos";
    }
}
