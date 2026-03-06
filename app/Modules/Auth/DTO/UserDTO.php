<?php

namespace App\Module\Auth\DTO;

/**
 * DTO para transferencia de datos de Usuario
 */
class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $nombre,
        public readonly string $apellidos,
        public readonly array $roles = [],
        public readonly bool $activo = true,
        public readonly ?\DateTimeImmutable $lastLogin = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            email: $data['email'],
            nombre: $data['nombre'],
            apellidos: $data['apellidos'],
            roles: $data['roles'] ?? [],
            activo: (bool)$data['activo'] ?? true,
            lastLogin: isset($data['last_login']) 
                ? new \DateTimeImmutable($data['last_login']) 
                : null,
        );
    }

    public function getFullName(): string
    {
        return "{$this->nombre} {$this->apellidos}";
    }
}
