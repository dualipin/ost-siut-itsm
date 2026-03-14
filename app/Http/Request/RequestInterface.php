<?php

namespace App\Http\Request;

interface RequestInterface
{
    public function input(string $key, mixed $default = null): mixed;
    public function all(): array;
    public function has(string $key): bool;
    public function method(): string;
    public function is(string $method): bool;
}
