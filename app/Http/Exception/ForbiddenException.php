<?php

namespace App\Http\Exception;

/**
 * Excepción para acceso denegado por falta de permisos
 * Se lanza cuando el usuario está autenticado pero no tiene los roles requeridos
 */
class ForbiddenException extends \Exception
{
    public function __construct(
        string $message = "No tienes permisos para acceder a este recurso",
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
