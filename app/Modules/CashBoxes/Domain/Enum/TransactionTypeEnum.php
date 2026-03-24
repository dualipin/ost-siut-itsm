<?php

declare(strict_types=1);

namespace App\Modules\CashBoxes\Domain\Enum;

enum TransactionTypeEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
}
