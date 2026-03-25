<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Enum;

enum AccessRoleEnum: string
{
    case ADMIN = 'admin';
    case OPERATOR = 'operator';
}
