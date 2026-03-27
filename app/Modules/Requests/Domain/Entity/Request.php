<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Entity;

use App\Modules\Requests\Domain\Enum\RequestStatusEnum;
use DateTimeImmutable;

final readonly class Request
{
    public function __construct(
        public int $requestId,
        public int $userId,
        public int $requestTypeId,
        public ?string $folio,
        public string $reason,
        public RequestStatusEnum $status,
        public ?string $adminNotes,
        public ?int $resolvedBy,
        public ?DateTimeImmutable $resolvedAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatusEnum::PENDIENTE;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->userId === $userId;
    }

    public function canTransitionTo(RequestStatusEnum $newStatus): bool
    {
        return in_array($newStatus, $this->status->allowedTransitions(), true);
    }
}
