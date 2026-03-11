<?php

namespace App\Http\Middleware;

use App\Http\Exception\UnauthorizedException;
use App\Shared\Context\UserContext;

/**
 * Middleware de autenticación - Requiere que el usuario esté autenticado
 */
final class AuthMiddleware extends BaseMiddleware
{
    public function __construct(UserContext $context)
    {
        parent::__construct($context);
    }

    /**
     * @throws UnauthorizedException
     */
    public function execute(): void
    {
        if (!$this->context->isAuthenticated()) {
            $this->deny("Access denied: authentication required");
        }
    }
}
