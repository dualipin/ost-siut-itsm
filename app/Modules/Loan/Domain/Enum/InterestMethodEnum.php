<?php

namespace App\Modules\Loan\Domain\Enum;

enum InterestMethodEnum: string
{
    case SimpleGerman = 'simple_aleman';
    case Compound = 'compuesto';
}
