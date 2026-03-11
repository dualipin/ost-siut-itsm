<?php

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\BankingData;
use App\Modules\User\Domain\ValueObject\PersonalInfo;
use App\Modules\User\Domain\ValueObject\WorkData;
use App\Shared\Domain\Enum\RoleEnum;
use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public RoleEnum $role,
        public bool $active,
        public PersonalInfo $personalInfo,
        public BankingData $bankingData,
        public WorkData $workData,
        public ?DateTimeImmutable $lastEntry = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function isComplete(): bool
    {
        return !empty($this->personalInfo->curp) &&
            !empty($this->bankingData->interbankCode);
    }
}
