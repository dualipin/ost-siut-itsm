<?php

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\Enum\DocumentStatusEnum;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use DateTimeImmutable;

final readonly class UserDocument
{
    public function __construct(
        public int $id,
        public int $userId,
        public DocumentTypeEnum $type,
        public string $filePath,
        public DocumentStatusEnum $status,
        public ?string $observations = null,
        public ?int $validatedBy = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function isValidated(): bool
    {
        return $this->status === DocumentStatusEnum::Validado;
    }
}
