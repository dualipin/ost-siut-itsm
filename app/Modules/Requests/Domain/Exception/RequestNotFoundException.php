<?php

declare(strict_types=1);

namespace App\Modules\Requests\Domain\Exception;

use RuntimeException;

final class RequestNotFoundException extends RuntimeException
{
    public function __construct(int $requestId)
    {
        parent::__construct("Solicitud con ID {$requestId} no encontrada.");
    }
}
