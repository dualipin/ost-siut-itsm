<?php

namespace App\Manejadores;

final class SesionProtegida
{
    /**
     * Protege una página asegurándose de que el usuario esté autenticado.
     * Si no lo está, redirige a la página de login con la URL original
     * como parámetro para redirigir después del login.
     * @param array<string> | null $roles Roles permitidos (no implementado en esta versión)
     * @param string $redirect
     * @return void
     */
    public static function proteger(?array $roles = [], string $redirect = '/cuenta/login.php'): void
    {
        if (!Sesion::verificarSesion()) {

            // Guardar la URL actual que el usuario quería visitar
            $urlActual = $_SERVER['REQUEST_URI'] ?? '';
            $destino = $redirect . '?redirect=' . urlencode($urlActual);

            header("Location: $destino");
            exit;
        }

//        if ($roles && !Sesion::tieneRol($roles)) {
//            // Si se implementan roles, aquí se podría manejar la redirección
//            // o mostrar un mensaje de acceso denegado.
//            header("HTTP/1.1 403 Forbidden");
//            echo "Acceso denegado.";
//            exit;
//        }
    }
}
