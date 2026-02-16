<?php

namespace App\Enums;

enum Roles: string
{
    case ADMINISTRADOR = 'administrador';
    case LIDER = 'lider';
    case AGREMIADO = 'agremiado';
    case NO_AGREMIADO = 'no_agremiado';
    case FINANZAS = 'finanzas';
}
