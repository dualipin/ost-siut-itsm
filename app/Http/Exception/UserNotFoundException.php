<?php

namespace App\Http\Exception;

/**
 * Excepción para cuando un usuario no existe
 */
class UserNotFoundException extends \Exception
{
    public function __construct(
        string $message = "Usuario no encontrado",
        int $code = 404,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
