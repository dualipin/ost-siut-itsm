<?php

namespace App\Manejadores;

use App\Entidades\EntidadMiembro;
use App\Modelos\Miembro;
use Exception;

final class Sesion
{
    /**
     * Inicia la sesión con parámetros seguros.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }


    public static function iniciarSesion(int $miembroId): void
    {
        self::iniciar();
        session_regenerate_id(true);

        $_SESSION['miembro_id'] = $miembroId;
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_activity'] = time();
    }

    public static function tieneSesionIniciada(): bool
    {
        return isset($_SESSION['miembro_id']);
    }

    public static function obtenerRol(): ?string
    {
        return $_SESSION['miembro_rol'] ?? null;
    }

    public static function idSesionAbierta(): ?int
    {
        return $_SESSION['miembro_id'] ?? null;
    }

    public static function sesionAbierta(): ?EntidadMiembro
    {
        $id = self::idSesionAbierta();
        return $id !== null ? Miembro::buscarMiembroId($id) : null;
    }

    /**
     * Verifica validez de la sesión en cada request.
     * @return bool true si es válida, false si se cerró.
     */
    public static function verificarSesion(): bool
    {
        if (!self::tieneSesionIniciada()) {
            return false;
        }

        return true;
    }

    private static function generarNuevoToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log("CSRF token error: " . $e->getMessage());
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    public static function obtenerCSRFToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generarNuevoToken();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validarCSRFToken(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || $token === null) {
            return false;
        }

        $valido = hash_equals($_SESSION['csrf_token'], $token);

        if ($valido) {
            $_SESSION['csrf_token'] = self::generarNuevoToken();
        }

        return $valido;
    }

    public static function cerrarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
        }
    }
}
