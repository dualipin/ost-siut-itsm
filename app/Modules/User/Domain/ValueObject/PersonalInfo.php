<?php

namespace App\Modules\User\Domain\ValueObject;

use DateTimeImmutable;

final readonly class PersonalInfo
{
    public function __construct(
        public string $name,
        public string $surnames,
        public ?string $curp = null,
        public ?DateTimeImmutable $birthdate = null,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $photo = null,
    ) {}
}
