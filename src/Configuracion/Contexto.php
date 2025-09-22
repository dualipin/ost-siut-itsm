<?php

namespace App\Configuracion;

use App\Entidades\EntidadMiembro;
use App\Manejadores\Sesion;

final class Contexto
{
    public static function obtenerCSRFToken(): string
    {
        return Sesion::obtenerCSRFToken();
    }

    public static function obtenerMiembro(): ?EntidadMiembro
    {
        return Sesion::sesionAbierta();
    }
}