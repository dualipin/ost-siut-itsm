<?php

namespace App\Modules\User\Domain\Enum;

enum DocumentTypeEnum: string
{
    case Afiliacion = "afiliacion";
    case Ine = "ine";
    case ComprobanteDomicilio = "comprobante_domicilio";
}
