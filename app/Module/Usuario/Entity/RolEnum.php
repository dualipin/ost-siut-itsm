<?php

namespace App\Module\Usuario\Entity;

enum RolEnum: string
{
    case Lider = "lider";
    case Admin = "admin";
    case Agremiado = "agremiado";
    case NoAgremiado = "no_agremiado";
    case Finanzas = "finanzas";
}
