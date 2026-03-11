<?php

declare(strict_types=1);

namespace App\Modules\User\Application\DTO;

use App\Shared\Domain\Enum\RoleEnum;

final readonly class UserSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $surnames,
        public string $email,
        public RoleEnum $role,
        public bool $active,
        public ?string $department = null,
    ) {}

    public function fullName(): string
    {
        return "{$this->name} {$this->surnames}";
    }
}
