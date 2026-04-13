<?php

namespace App\Modules\Loan\Domain\Enum;

enum PaymentStatusEnum: string
{
    case Pending = 'pendiente';
    case Paid = 'pagado';
    case Pico = 'pico';
    case Overdue = 'vencido';
}
