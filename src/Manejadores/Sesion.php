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
            ini_set('session.cookie_secure', '1');   // Solo HTTPS
            ini_set('session.cookie_httponly', '1'); // No accesible desde JS
            ini_set('session.use_strict_mode', '1'); // Evita reutilizar IDs inválidos
            ini_set('session.use_trans_sid', '0');   // No pasar ID en URL

            session_start();
        }
    }

    /**
     * Inicia sesión de un miembro de forma segura.
     */
    public static function iniciarSesion(int $miembroId): void
    {
        self::iniciar();

        session_regenerate_id(true);

        $_SESSION['miembro_id'] = $miembroId;
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_activity'] = time();
    }

    /**
     * Verifica si existe una sesión iniciada.
     */
    public static function tieneSesionIniciada(): bool
    {
        return isset($_SESSION['miembro_id']);
    }

    /**
     * Devuelve el ID del miembro logueado o null.
     */
    public static function idSesionAbierta(): ?int
    {
        return $_SESSION['miembro_id'] ?? null;
    }

    /**
     * Devuelve el objeto del miembro logueado.
     */
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

        // Actualizar última actividad
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Genera un nuevo token CSRF.
     */
    private static function generarNuevoToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            error_log("CSRF token error: " . $e->getMessage());
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    /**
     * Obtiene el token CSRF actual o genera uno nuevo.
     */
    public static function obtenerCSRFToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generarNuevoToken();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida el token CSRF y rota si es válido.
     */
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

    /**
     * Cierra la sesión limpiamente.
     */
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
