<?php

namespace App\Manejadores;

final class SesionProtegida
{
    /**
     * Protege una página asegurándose de que el usuario esté autenticado.
     * Si no lo está, redirige a la página de login con la URL original
     * como parámetro para redirigir después del login.
     * @param string $redirect
     * @return void
     */
    public static function proteger(string $redirect = '/cuenta/login.php'): void
    {
        if (!Sesion::verificarSesion()) {
            // Guardar la URL actual que el usuario quería visitar
            $urlActual = $_SERVER['REQUEST_URI'] ?? '';
            $destino = $redirect . '?redirect=' . urlencode($urlActual);

            header("Location: $destino");
            exit;
        }
    }
}
