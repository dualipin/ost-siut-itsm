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
        if (strlen($token) !== self::TokenHexLength) {
            return false;
        }

        $formatted = sprintf(
            "%s-%s-%s-%s-%s",
            substr($token, 0, 8),
            substr($token, 8, 4),
            substr($token, 12, 4),
            substr($token, 16, 4),
            substr($token, 20, 12),
        );

        return Uuid::isValid($formatted);
    }
}