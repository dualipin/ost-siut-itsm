<?php

namespace App\Modules\User\Domain\Enum;

enum DocumentStatusEnum: string
{
    case Pending = "pendiente";
    case Validate = "validado";
    case Rejected = "rechazado";
}
