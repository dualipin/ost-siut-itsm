<?php

namespace App\Modules\Loan\Domain\Enum;

enum ReceiptTypeEnum: string
{
    case Disbursement = 'desembolso';
    case RegularPayment = 'pago_regular';
    case ExtraordinaryPayment = 'pago_extraordinario';
    case DefaultCharge = 'cargo_moratorio';
    case Adjustment = 'ajuste';
}
