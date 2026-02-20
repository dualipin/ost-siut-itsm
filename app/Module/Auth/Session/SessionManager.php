<?php

namespace App\Module\Auth\Session;

/**
 * Gestor de sesiones para autenticación
 */
class SessionManager
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const SESSION_ROLES_KEY = 'auth_user_roles';
    private const SESSION_PERMISSIONS_KEY = 'auth_user_permissions';
    private const SESSION_TIMESTAMP_KEY = 'auth_session_created';

    public function __construct(
        private int $sessionTimeout = 3600, // 1 hora
    ) {
        $this->initSession();
    }

    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Guarda la información del usuario en sesión
     */
    public function saveUserSession(int $userId, array $roles = [], array $permissions = []): void
    {
        $_SESSION[self::SESSION_USER_KEY] = $userId;
        $_SESSION[self::SESSION_ROLES_KEY] = $roles;
        $_SESSION[self::SESSION_PERMISSIONS_KEY] = $permissions;
        $_SESSION[self::SESSION_TIMESTAMP_KEY] = time();
    }

    /**
     * Obtiene el ID del usuario de la sesión
     */
    public function getUserId(): ?int
    {
        if (!isset($_SESSION[self::SESSION_USER_KEY])) {
            return null;
        }

        // Verificar timeout
        if (!$this->isSessionValid()) {
            $this->destroySession();
            return null;
        }

        return $_SESSION[self::SESSION_USER_KEY];
    }

    /**
     * Obtiene los roles del usuario de la sesión
     */
    public function getUserRoles(): array
    {
        return $_SESSION[self::SESSION_ROLES_KEY] ?? [];
    }

    /**
     * Obtiene los permisos del usuario de la sesión
     */
    public function getUserPermissions(): array
    {
        return $_SESSION[self::SESSION_PERMISSIONS_KEY] ?? [];
    }

    /**
     * Verifica si la sesión es válida
     */
    public function isSessionValid(): bool
    {
        if (!isset($_SESSION[self::SESSION_USER_KEY])) {
            return false;
        }

        $createdAt = $_SESSION[self::SESSION_TIMESTAMP_KEY] ?? 0;
        return (time() - $createdAt) < $this->sessionTimeout;
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->getUserId() !== null;
    }

    /**
     * Renueva la sesión (actualiza el timestamp)
     */
    public function renewSession(): void
    {
        if ($this->isAuthenticated()) {
            $_SESSION[self::SESSION_TIMESTAMP_KEY] = time();
        }
    }

    /**
     * Destruye la sesión
     */
    public function destroySession(): void
    {
        unset($_SESSION[self::SESSION_USER_KEY]);
        unset($_SESSION[self::SESSION_ROLES_KEY]);
        unset($_SESSION[self::SESSION_PERMISSIONS_KEY]);
        unset($_SESSION[self::SESSION_TIMESTAMP_KEY]);
    }

    /**
     * Destruye completamente la sesión
     */
    public function logout(): void
    {
        $this->destroySession();
        session_destroy();
    }

    /**
     * Establece el timeout de sesión
     */
    public function setSessionTimeout(int $seconds): void
    {
        $this->sessionTimeout = $seconds;
    }
}
