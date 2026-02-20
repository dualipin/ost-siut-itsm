<?php

namespace App\Module\Auth\Exception;

/**
 * Excepción para errores de autenticación
 */
class AuthenticationException extends \Exception
{
    public function __construct(
        string $message = 'Error de autenticación',
        int $code = 401,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
