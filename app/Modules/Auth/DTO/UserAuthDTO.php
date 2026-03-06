<?php
namespace App\Module\Auth\DTO;

use App\Module\Auth\Enum\RoleEnum;
use DateTimeImmutable;

final readonly class UserAuthDTO
{
    public function __construct(
        public int $id,
        public string $email,
        public string $passwordHash,
        public RoleEnum $rol,
        public bool $active,
        public ?DateTimeImmutable $ultimoIngreso = null,
    ) {}
}
