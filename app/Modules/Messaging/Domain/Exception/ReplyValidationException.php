<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Domain\Exception;

use RuntimeException;

final class ReplyValidationException extends RuntimeException
{
    public static function emptyBody(): self
    {
        return new self('El cuerpo de la respuesta no puede estar vacío.');
    }

    public static function threadNotFound(int $id): self
    {
        return new self("No se encontró el hilo de mensaje #{$id}.");
    }

    public static function invalidThreadType(string $expected, string $actual): self
    {
        return new self("Se esperaba un hilo de tipo '{$expected}', pero se recibió '{$actual}'.");
    }

    public static function missingExternalEmail(int $threadId): self
    {
        return new self("El hilo #{$threadId} no tiene un correo electrónico de remitente externo.");
    }
}
