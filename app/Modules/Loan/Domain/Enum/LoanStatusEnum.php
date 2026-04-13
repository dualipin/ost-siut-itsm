<?php

namespace App\Modules\Loan\Domain\Enum;

enum LoanStatusEnum: string
{
    case Draft = 'borrador';
    case Submitted = 'solicitado';
    case Approved = 'aprobado';
    case Rejected = 'rechazado';
    case OnHold = 'en_espera';
    case Active = 'activo';
    case Liquidated = 'liquidado';
    case Restructured = 'reestructurado';
}
