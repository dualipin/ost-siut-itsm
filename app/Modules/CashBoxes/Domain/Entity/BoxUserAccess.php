<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Entity;

use App\Modules\CashBoxes\Domain\Enum\AccessRoleEnum;
use DateTimeImmutable;

final readonly class BoxUserAccess
{
    public function __construct(
        public int $accessId,
        public int $boxId,
        public int $userId,
        public AccessRoleEnum $role,
        public int $grantedBy,
        public bool $active,
        public DateTimeImmutable $grantedAt,
        public ?DateTimeImmutable $revokedAt = null,
    ) {
    }
}
