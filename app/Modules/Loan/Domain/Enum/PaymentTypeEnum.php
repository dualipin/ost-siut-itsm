<?php

namespace App\Modules\Loan\Domain\Enum;

enum PaymentTypeEnum: string
{
    case Advance = 'anticipo';
    case PrincipalPayment = 'abono_capital';
    case TotalLiquidation = 'liquidacion_total';
}
