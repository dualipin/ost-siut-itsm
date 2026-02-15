<?php

namespace App\Infrastructure\Templating\Context;

use App\Module\Sindicato\DTO\SindicatoInfo;

final readonly class SindicatoContext
{
    /**
     * @param SindicatoInfo[] $sindicatos
     */
    public function __construct(
        public array $sindicatos
    ) {
    }
}