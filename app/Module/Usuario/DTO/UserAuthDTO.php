<?php
namespace App\Module\Usuario\DTO;

use App\Module\Usuario\Entity\RolEnum;

class UserAuthDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public string $password,
        public RolEnum $rol,
        public bool $active,
    ) {}
}
