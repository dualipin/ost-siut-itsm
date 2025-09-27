<?php

namespace App\Manejadores;

use App\Entidades\EntidadMiembro;
use App\Modelos\Miembro;

final class Sesion
{
    private const int SESSION_LIFETIME = 1800; // 30 minutos

    /**
     * Inicia la sesión con parámetros seguros.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Carpeta de sesiones controlada por ti
            $rutaSesiones = __DIR__ . '/../../privado/sesiones';
            if (!is_dir($rutaSesiones)) {
                mkdir($rutaSesiones, 0700, true);
            }
            session_save_path($rutaSesiones);

            // Configuración de cookies y seguridad
            ini_set('session.cookie_secure', '1');     // solo HTTPS
            ini_set('session.cookie_httponly', '1');   // no accesible por JS
            ini_set('session.use_strict_mode', '1');   // IDs estrictos
            ini_set('session.use_trans_sid', '0');     // sin ID en URL

            // Control de expiración y limpieza
            ini_set('session.gc_maxlifetime', (string) self::SESSION_LIFETIME);
            ini_set('session.gc_probability', '1');    // 1%
            ini_set('session.gc_divisor', '100');      // 1/100 = 1% de probabilidad
            ini_set('session.cookie_lifetime', (string) self::SESSION_LIFETIME);

            session_start();

            // 🔹 Renovar cookie en cada request (sesión viva mientras haya actividad)
            setcookie(session_name(), session_id(), [
                    'expires'  => time() + self::SESSION_LIFETIME,
                    'path'     => ini_get('session.cookie_path'),
                    'domain'   => ini_get('session.cookie_domain'),
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
            ]);
        }
    }

    public static function iniciarSesion(int $miembroId): void
    {
        self::iniciar();
        session_regenerate_id(true);

        $_SESSION['miembro_id']   = $miembroId;
        $_SESSION['user_ip']      = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent']   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_activity'] = time();
    }

    public static function tieneSesionIniciada(): bool
    {
        return isset($_SESSION['miembro_id']);
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

        // Validar IP y User-Agent
        if (($_SESSION['user_ip'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '') ||
                ($_SESSION['user_agent'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::cerrarSesion();
            return false;
        }

        // Expiración por inactividad
        if (isset($_SESSION['last_activity']) &&
                (time() - $_SESSION['last_activity'] > self::SESSION_LIFETIME)) {
            self::cerrarSesion();
            return false;
        }

        // 🔹 Actualizar última actividad
        $_SESSION['last_activity'] = time();

        return true;
    }

    private static function generarNuevoToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Exception $e) {
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
