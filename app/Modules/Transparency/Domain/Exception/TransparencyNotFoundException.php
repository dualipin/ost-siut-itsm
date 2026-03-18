<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Exception;

use DomainException;

final class TransparencyNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("No se encontró el documento de transparencia con el ID: {$id}");
    }
}
