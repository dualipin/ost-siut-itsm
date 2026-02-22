<?php

namespace App\Http\Middleware;

use App\Shared\Context\UserContext;

/**
 * Factory para crear middlewares de forma sencilla
 */
final readonly class MiddlewareFactory
{
    public function __construct(private UserContext $userContext) {}
    /**
     * Crea un middleware de autenticación
     */
    public function createAuthMiddleware(): AuthMiddleware
    {
        return new AuthMiddleware($this->userContext);
    }

    /**
     * Crea un middleware de roles
     */
    public function createRoleMiddleware(array $roles): RoleMiddleware
    {
        $middleware = new RoleMiddleware($this->authService);
        return $middleware->setRequiredRoles($roles);
    }
}
