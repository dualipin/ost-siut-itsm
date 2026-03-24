<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Enum;

enum ContributionCategoryEnum: string
{
    case ORDINARY = 'ordinary';
    case EXTRAORDINARY = 'extraordinary';
    case TRUST_STAFF = 'trust_staff';
    case RETIRED = 'retired';
    case UNION_LEAVE = 'union_leave';
    case OTHER = 'other';
}
