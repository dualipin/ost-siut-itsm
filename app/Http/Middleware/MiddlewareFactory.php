<?php

namespace App\Http\Middleware;

use App\Http\Response\Redirector;
use App\Module\Auth\Enum\RolEnum;
use App\Shared\Context\UserContext;

/**
 * Factory para crear middlewares de forma sencilla
 */
final readonly class MiddlewareFactory
{
    public function __construct(
        private UserContext $userContext,
        private Redirector $redirector,
    ) {}
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
    public function role(RolEnum ...$roles): RoleMiddleware
    {
        return new RoleMiddleware($this->userContext, ...$roles);
    }

    public function runOrRedirect(
        BaseMiddleware $middleware,
        string $redirectTo = "/cuentas/login.php",
    ): void {
        if (!$middleware->execute()) {
            $this->redirector->to($redirectTo)->send();
        }
    }
}
