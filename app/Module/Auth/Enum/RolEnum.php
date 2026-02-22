<?php

namespace App\Module\Auth\Enum;

enum RolEnum: string
{
    case Lider = "lider";
    case Admin = "administrador";
    case Agremiado = "agremiado";
    case NoAgremiado = "no_agremiado";
    case Finanzas = "finanzas";
}
