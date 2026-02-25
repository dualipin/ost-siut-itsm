<?php

namespace App\Http\Middleware;

use App\Http\Exception\UnauthorizedException;
use App\Shared\Context\ContextInterface;

/**
 * Middleware base para proteger rutas
 */
abstract class BaseMiddleware
{
    public function __construct(protected ContextInterface $context) {}

    /**
     * Ejecuta el middleware, lanza UnauthorizedException si falla
     * @throws UnauthorizedException
     */
    abstract public function execute(): void;

    /**
     * @throws UnauthorizedException
     */
    protected function deny(string $message = "Access denied"): never
    {
        throw new UnauthorizedException($message);
    }
}
