<?php

namespace App\Module\Auth\DTO;

use App\Module\Auth\Enum\AuthLogActionEnum;
use DateTimeImmutable;

final readonly class AuthLogDTO
{
    public function __construct(
        public AuthLogActionEnum $action,
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
