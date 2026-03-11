<?php

namespace App\Modules\Auth\Domain\Entity;

use App\Shared\Domain\Enum\RoleEnum;

final readonly class UserCredential
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public RoleEnum $role,
        public bool $isActive,
    ) {}

    public function verifyPassword(string $password): bool
    {
        if (!$this->isActive) {
            return false;
        }
        return password_verify($password, $this->passwordHash);
    }
}
