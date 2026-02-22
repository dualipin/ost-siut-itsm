<?php

namespace App\Http\Middleware;

/**
 * Middleware de autenticación - Requiere que el usuario esté autenticado
 */
class AuthMiddleware extends BaseMiddleware
{
    public function execute(): bool
    {
        if (!$this->context->get()) {
            return $this->deny(
                "Debe estar autenticado para acceder a este recurso",
            );
        }

        return true;
    }
}
