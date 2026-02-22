<?php

namespace App\Http\Exception;

/**
 * Excepción para demasiados intentos de autenticación
 */
class TooManyAttemptsException extends \Exception
{
    public function __construct(
        string $message = "Demasiados intentos fallidos. Intenta más tarde",
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
