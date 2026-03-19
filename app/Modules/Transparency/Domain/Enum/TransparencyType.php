<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Enum;

enum TransparencyType: string
{
    case FINANCIERO = 'FINANCIERO';
    case ADMINISTRATIVO = 'ADMINISTRATIVO';
    case LEGAL = 'LEGAL';
    case SINDICAL = 'SINDICAL';
    case GESTORIA = 'GESTORIA';
    case GREMIALES = 'GREMIALES';
    case TRAMITES = 'TRAMITES';
    case MINUTAS = 'MINUTAS';
    case OTRO = 'OTRO';
}
