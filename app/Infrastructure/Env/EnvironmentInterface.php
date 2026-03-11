<?php

namespace App\Infrastructure\Env;

interface EnvironmentInterface
{
    public function get(
        string $key,
        string|int|bool|null $default = null,
    ): string|int|bool|null;
}
