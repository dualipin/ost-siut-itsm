<?php

namespace App\Http\Exception;

/**
 * Excepción para cuando un rol no existe
 */
class RoleNotFoundException extends \Exception
{
    public function __construct(
        string $message = "Rol no encontrado",
        int $code = 404,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
