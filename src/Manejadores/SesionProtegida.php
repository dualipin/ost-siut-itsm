<?php

namespace App\Manejadores;


use App\Servicios\ServicioLatte;

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
    }

    /**
     * Verifica si el rol del usuario está en la lista de roles autorizados.
     * Si no lo está, muestra una página de "Prohibido" y termina la ejecución.
     * @param array<string> | null $roles Roles permitidos
     * @return void
     */
    public static function rolesAutorizados(?array $roles): void
    {
        $rol = Sesion::sesionAbierta()->getRol();

        if ($rol && $roles && !in_array($rol, $roles, true)) {
            header("HTTP/1.1 403 Forbidden");

            if (str_contains($_SERVER['REQUEST_URI'], '/aplicacion/')) {
                ServicioLatte::renderizar(__DIR__ . '/../../aplicacion/plantillas/prohibido.latte');
            } else {
                ServicioLatte::renderizar(__DIR__ . '/../../prohibido.latte');
            }

            exit;
        }
    }
}
