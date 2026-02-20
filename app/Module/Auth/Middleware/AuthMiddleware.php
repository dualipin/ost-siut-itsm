<?php

namespace App\Module\Auth\Middleware;

/**
 * Middleware de autenticación - Requiere que el usuario esté autenticado
 */
class AuthMiddleware extends BaseMiddleware
{
    public function execute(): bool
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->deny('Debe estar autenticado para acceder a este recurso');
        }

        return true;
    }
}
