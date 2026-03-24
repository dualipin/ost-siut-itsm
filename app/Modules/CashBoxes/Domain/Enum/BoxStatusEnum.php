<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Enum;

enum BoxStatusEnum: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
