<?php

declare(strict_types=1);

namespace App\Modules\User\Application\DTO;

use App\Shared\Domain\Enum\RoleEnum;
use DateTimeImmutable;

final readonly class CreateUser
{
    public function __construct(
        public string $email,
        public string $password,
        public string $name,
        public string $surnames,
        public RoleEnum $role = RoleEnum::NoAgremiado,
        public bool $active = true,
        public ?string $curp = null,
        public ?DateTimeImmutable $birthdate = null,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $photo = null,
        public ?string $bankName = null,
        public ?string $interbankCode = null,
        public ?string $bankAccount = null,
        public ?string $category = null,
        public ?string $department = null,
        public ?string $nss = null,
        public float $salary = 0.0,
        public ?DateTimeImmutable $workStartDate = null,
    ) {}
}
