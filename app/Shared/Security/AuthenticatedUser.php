<?php

namespace App\Shared\Security;

use App\Shared\Domain\Enum\RoleEnum;

final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $email,
        public RoleEnum $role,
    ) {}
}
