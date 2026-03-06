<?php

namespace App\Infrastructure\Session;

/**
 * @template T
 */
interface SessionInterface
{
    public function start(): void;

    /**
     * @param string $key
     * @param T|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @param string $key
     * @param T $value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    public function regenerate(): void;

    public function destroy(): void;

    public function isStarted(): bool;
}
