<?php

namespace App\Module\Auth\Exception;

/**
 * Excepción para errores de autorización
 */
class UnauthorizedException extends \Exception
{
    public function __construct(
        string $message = 'Acceso denegado',
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
