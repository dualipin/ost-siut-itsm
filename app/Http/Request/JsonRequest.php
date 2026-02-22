<?php

namespace App\Http\Request;

final readonly class JsonRequest
{
    private array $body;
    private array $query;
    private array $headers;

    public function __construct()
    {
        $this->body = $this->parseBody();
        $this->query = $_GET ?? [];
        $this->headers = $this->parseHeaders();
    }

    // --- Body (JSON payload) ---

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body);
    }

    public function only(string ...$keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    public function except(string ...$keys): array
    {
        return array_diff_key($this->body, array_flip($keys));
    }

    // --- Query string ---

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    // --- Headers ---

    public function header(string $name, mixed $default = null): mixed
    {
        // Normalizamos a mayúsculas con guiones
        $normalized = strtoupper(str_replace("-", "_", $name));
        return $this->headers[$normalized] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header("Authorization");

        if ($auth && str_starts_with($auth, "Bearer ")) {
            return substr($auth, 7);
        }

        return null;
    }

    public function isJson(): bool
    {
        $contentType = $this->header("Content_Type", "");
        return str_contains($contentType, "application/json");
    }

    public function method(): string
    {
        return strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
    }

    public function is(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    // --- Internals ---

    private function parseBody(): array
    {
        $raw = file_get_contents("php://input");

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $decoded;
    }

    private function parseHeaders(): array
    {
        if (function_exists("getallheaders")) {
            $raw = getallheaders();
            return array_combine(
                array_map(
                    fn($k) => strtoupper(str_replace("-", "_", $k)),
                    array_keys($raw),
                ),
                array_values($raw),
            );
        }

        // Fallback para entornos sin getallheaders (nginx, CLI)
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, "HTTP_")) {
                $headers[substr($key, 5)] = $value;
            }
        }

        return $headers;
    }
}
