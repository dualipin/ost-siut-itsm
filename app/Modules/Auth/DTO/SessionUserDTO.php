<?php

namespace App\Module\Auth\DTO;

use App\Module\Auth\Enum\RoleEnum;

final readonly class SessionUserDTO
{
    public function __construct(
        public int $id,
        public string $email,
        public RoleEnum $rol,
    ) {}
}
