<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Exception;

use DomainException;

final class QuestionValidationException extends DomainException
{
    public static function requiredFields(): self
    {
        return new self("Todos los campos (nombre, correo y mensaje) son obligatorios.");
    }

    public static function invalidEmail(): self
    {
        return new self("El formato del correo electrónico no es válido.");
    }
}
