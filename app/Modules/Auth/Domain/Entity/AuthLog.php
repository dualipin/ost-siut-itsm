<?php

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\Enum\AuthLogActionEnum;
use DateTimeImmutable;

final readonly class AuthLog
{
    public function __construct(
        public AuthLogActionEnum $action,
        public bool $success = false,
        public ?int $id = null,
        public ?int $userId = null,
        public ?string $email = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $errorMessage = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
