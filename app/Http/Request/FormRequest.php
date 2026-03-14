<?php

declare(strict_types=1);

namespace App\Http\Request;

use function array_intersect_key;
use function array_key_exists;
use function array_map;

final readonly class FormRequest implements RequestInterface
{
    /**
     * El constructor ahora es "puro". No sabe nada de $_POST o $_GET.
     * Simplemente recibe los datos. Esto hace que mockearlo en tests sea trivial.
     */
    public function __construct(
        private array $data,
        private array $files,
        private string $method,
    ) {}

    /**
     * Factory Method: Este es el ÚNICO lugar de tu app que toca las variables globales.
     * Lo llamas desde tu public/index.php o tu enrutador.
     */
    public static function capture(): self
    {
        $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");

        $data = match ($method) {
            "POST" => $_POST ?? [],
            "GET" => $_GET ?? [],
            default => [...$_GET ?? [], ...$_POST ?? []],
        };

        return new self(data: $data, files: $_FILES ?? [], method: $method);
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
        // Nota: Reemplazar comas por puntos está bien para UX,
        // pero ten cuidado si luego implementas internacionalización (i18n).
        return (float) str_replace(
            ",",
            ".",
            (string) ($this->data[$key] ?? $default),
        );
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function is(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isSubmitted(): bool
    {
        return $this->is("POST");
    }
}
