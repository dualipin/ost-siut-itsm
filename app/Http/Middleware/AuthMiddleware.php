<?php

namespace App\Http\Middleware;

use App\Shared\Context\ContextInterface;
use App\Shared\Context\UserContext;

/**
 * Middleware de autenticación - Requiere que el usuario esté autenticado
 */
class AuthMiddleware extends BaseMiddleware
{
    /**
     * @param UserContext $context
     */
    public function __construct(UserContext $context)
    {
        parent::__construct($context);
    }

    public function execute(): bool
    {
        if (!$this->context->isAuthenticated()) {
            return $this->deny(
                "Debes iniciar sesión para acceder a este recurso",
            );
        }

        return true;
    }
}
