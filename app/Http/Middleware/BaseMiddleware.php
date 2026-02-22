<?php

namespace App\Http\Middleware;

use App\Http\Exception\UnauthorizedException;
use App\Module\Auth\Service\AuthenticationService;

/**
 * Middleware base para proteger rutas
 */
abstract class BaseMiddleware
{
    protected UnauthorizedException $lastException;

    public function __construct(protected AuthenticationService $authService) {}

    /**
     * Ejecuta el middleware
     */
    abstract public function execute(): bool;

    /**
     * Obtiene la última excepción generada
     */
    public function getLastException(): ?UnauthorizedException
    {
        return $this->lastException ?? null;
    }

    /**
     * Genera un error 403
     */
    protected function deny(string $message = "Acceso denegado"): bool
    {
        $this->lastException = new UnauthorizedException($message);
        return false;
    }
}
