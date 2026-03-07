<?php

namespace App\Modules\Auth\Domain\Enum;

enum RoleEnum: string
{
    case Lider = "lider";
    case Admin = "administrador";
    case Agremiado = "agremiado";
    case NoAgremiado = "no_agremiado";
    case Finanzas = "finanzas";
}
