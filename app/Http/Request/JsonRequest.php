<?php

declare(strict_types=1);

namespace App\Http\Request;

use function array_intersect_key;
use function array_key_exists;
use function array_map;

final readonly class JsonRequest implements RequestInterface
{
    /**
     * El constructor es 100% puro. Recibe los datos ya procesados.
     */
    public function __construct(
        private array $body,
        private array $query,
        private array $headers,
        private string $method,
    ) {}

    /**
     * Factory Method: El único lugar que toca globales y el stream de entrada.
     */
    public static function capture(): self
    {
        $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
        $query = $_GET ?? [];
        $headers = self::parseHeaders();
        $body = self::parseBody();

        return new self(
            body: $body,
            query: $query,
            headers: $headers,
            method: $method,
        );
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
        $normalized = strtoupper(str_replace("-", "_", $name));
        return $this->headers[$normalized] ?? $default;
    }

    public function isJson(): bool
    {
        $contentType = $this->header("CONTENT_TYPE", ""); // Corregido: Guión bajo en lugar de guión si lo normalizas
        return str_contains(strtolower($contentType), "application/json");
    }

    public function method(): string
    {
        return $this->method;
    }

    public function is(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    // --- Funciones estáticas internas de extracción ---

    private static function parseBody(): array
    {
        // Leer el stream de entrada solo una vez y en el Factory
        $raw = file_get_contents("php://input");

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // Opcional: Podrías lanzar una excepción como InvalidJsonException aquí
        }

        return $decoded;
    }

    private static function parseHeaders(): array
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

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, "HTTP_")) {
                $headers[substr($key, 5)] = $value;
            } elseif (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH"])) {
                // Asegurar que Content-Type se capture si no tiene el prefijo HTTP_ (común en Nginx/FPM)
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
