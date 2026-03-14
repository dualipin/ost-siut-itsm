<?php

namespace App\Http\Request;

use function array_intersect_key;
use function array_key_exists;
use function array_map;

final readonly class FormRequest
{
    private array $data;

    public function __construct()
    {
        $this->data = match ($this->method()) {
            "POST" => $_POST ?? [],
            "GET" => $_GET ?? [],
            default => [...$_GET ?? [], ...$_POST ?? []],
        };
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function all(): array
    {
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $this->data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== "";
    }

    public function only(string ...$keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(string ...$keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function boolean(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key] !== "0";
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) ($this->data[$key] ?? $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) str_replace(",", ".", $this->data[$key] ?? $default);
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function method(): string
    {
        return strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
    }

    public function is(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isSubmitted(): bool
    {
        return $this->is("POST");
    }
}
