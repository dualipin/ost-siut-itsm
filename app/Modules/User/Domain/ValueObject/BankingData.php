<?php

namespace App\Modules\User\Domain\ValueObject;

final readonly class BankingData
{
    public function __construct(
        public ?string $bankName = null,
        public ?string $interbankCode = null,
        public ?string $bankAccount = null,
    ) {}
}
