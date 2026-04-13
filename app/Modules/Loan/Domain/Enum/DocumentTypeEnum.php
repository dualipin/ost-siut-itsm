<?php

namespace App\Modules\Loan\Domain\Enum;

enum DocumentTypeEnum: string
{
    case PromissoryNote = 'pagare';
    case ConsentForm = 'anuencia';
    case ApplicationForm = 'solicitud';
    case AmortizationSchedule = 'corrida_financiera';
    case AccountStatement = 'estado_cuenta';
}
