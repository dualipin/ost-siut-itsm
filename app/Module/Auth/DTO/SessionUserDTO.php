<?php

namespace App\Module\Auth\DTO;

use App\Module\Auth\Enum\RolEnum;

final readonly class SessionUserDTO
{
    public function __construct(public int $id, public RolEnum $rol) {}
}
