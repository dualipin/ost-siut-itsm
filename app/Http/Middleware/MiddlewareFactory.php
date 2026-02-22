<?php

namespace App\Http\Middleware;

use App\Module\Auth\Middleware\AuthenticationService;

/**
 * Factory para crear middlewares de forma sencilla
 */
class MiddlewareFactory
{
    public function __construct(private AuthenticationService $authService) {}

    /**
     * Crea un middleware de autenticación
     */
    public function createAuthMiddleware(): AuthMiddleware
    {
        return new AuthMiddleware($this->authService);
    }

    /**
     * Crea un middleware de roles
     */
    public function createRoleMiddleware(array $roles): RoleMiddleware
    {
        $middleware = new RoleMiddleware($this->authService);
        return $middleware->setRequiredRoles($roles);
    }

    /**
     * Crea un middleware de permisos
     */
    public function createPermissionMiddleware(
        array $permissions,
        bool $requireAll = false,
    ): PermissionMiddleware {
        $middleware = new PermissionMiddleware($this->authService);
        return $middleware->setRequiredPermissions($permissions, $requireAll);
    }
}
