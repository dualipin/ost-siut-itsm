<?php

namespace App\Http\Middleware;

use App\Shared\Domain\Enum\RoleEnum;
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
    public function auth(): AuthMiddleware
    {
        return new AuthMiddleware($this->userContext);
    }
    /**
     * Crea un middleware de roles
     */
    public function role(RoleEnum ...$roles): RoleMiddleware
    {
        return new RoleMiddleware($this->userContext, ...$roles);
    }
}
