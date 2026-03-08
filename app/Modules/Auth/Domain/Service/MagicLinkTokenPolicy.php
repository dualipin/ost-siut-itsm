<?php

namespace App\Modules\Auth\Domain\Service;

use Ramsey\Uuid\Uuid;

use function strlen;
use function str_replace;

final class MagicLinkTokenPolicy
{
    private const int TokenHexLength = 32;

    public function generate(): string
    {
        return str_replace("-", "", Uuid::uuid4()->toString());
    }

    public function isValid(string $token): bool
    {
        return ctype_xdigit($token) && strlen($token) === self::TokenHexLength;
    }
}