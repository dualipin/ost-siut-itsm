<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Exception;

use RuntimeException;

final class ContactMessageValidationException extends RuntimeException
{
    public static function requiredFields(): self
    {
        return new self(
            "Por favor completa todos los campos requeridos (nombre, correo y mensaje).",
        );
    }

    public static function invalidEmail(): self
    {
        return new self("Por favor proporciona un correo electrónico válido.");
    }

    public static function invalidPhone(): self
    {
        return new self("El teléfono debe tener exactamente 10 dígitos.");
    }
}
