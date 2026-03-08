<?php

namespace App\Shared\Security;

use App\Modules\Auth\Domain\Enum\RoleEnum;

final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $email,
        public RoleEnum $role,
    ) {}
}
