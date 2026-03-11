<?php

namespace App\Modules\Messaging\Domain\Enum;

enum MessageTypeEnum: string
{
    case GeneralContact = "contacto_general";
    case TransparencyDoubt = "transparencia_duda";
    case ComplaintBox = "buzon_queja";
}
