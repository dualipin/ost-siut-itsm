<?php

namespace App\Module\Auth\DTO;

/**
 * DTO para transferencia de datos de Rol
 */
class RoleDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly array $permissions = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            name: $data['name'],
            description: $data['description'],
            permissions: $data['permissions'] ?? [],
        );
    }
}
