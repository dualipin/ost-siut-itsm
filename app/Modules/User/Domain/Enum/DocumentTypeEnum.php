<?php

namespace App\Modules\User\Domain\Enum;

enum DocumentTypeEnum: string
{
    case Afiliacion = "afiliacion";
    case Ine = "ine";
    case ComprobanteDomicilio = "comprobante_domicilio";
    case Curp = "curp";
    case ComprobantePago = "comprobante_pago";
}
