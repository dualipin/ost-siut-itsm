<?php

namespace App\Manejadores;

final class SesionProtegida
{
    /**
     * Verifica si el usuario tiene sesión válida.
     * Si no, redirige al login (o a la ruta indicada).
     */
    public static function proteger(string $redirect = '/cuenta/login.php'): void
    {
        Sesion::iniciar();

        if (!Sesion::verificarSesion()) {
            header("Location: $redirect");
            exit;
        }
    }
}
