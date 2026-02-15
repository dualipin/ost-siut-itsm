<?php

namespace App\Infrastructure\Env;

class Environment implements EnvironmentInterface
{
    public function get(
        string $key,
        bool|int|string|null $default = null,
    ): string|int|bool|null {
        $value = $_ENV[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        // $_ENV siempre devuelve strings; preservamos el tipo del default como hint
        return match (true) {
            is_int($default) => (int) $value,
            is_bool($default) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
