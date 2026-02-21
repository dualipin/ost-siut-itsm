<?php

namespace App\Module\Usuario\Entity;

use DateTimeImmutable;

final readonly class UsuarioLog
{
    public function __construct(
        public string $action,
        public bool $success = false,
        public ?int $id = null,
        public ?string $usuarioId = null,
        public ?string $email = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $errorMessage = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
