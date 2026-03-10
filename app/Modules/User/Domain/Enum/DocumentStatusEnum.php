<?php

namespace App\Modules\User\Domain\Enum;

enum DocumentStatusEnum: string
{
    case Pendiente = "pendiente";
    case Validado = "validado";
    case Rechazado = "rechazado";
}
