<?php

namespace App\Http\Response;

final readonly class JsonResponse
{
    private function __construct(
        private mixed $data,
        private int $status,
        private array $headers = [],
    ) {}

    // Named constructors semánticos
    public static function ok(mixed $data = null): self
    {
        return new self($data, 200);
    }

    public static function created(mixed $data = null): self
    {
        return new self($data, 201);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public static function badRequest(
        string $message,
        mixed $errors = null,
    ): self {
        return new self(self::errorBody($message, $errors), 400);
    }

    public static function unauthorized(string $message = "No autorizado"): self
    {
        return new self(self::errorBody($message), 401);
    }

    public static function forbidden(string $message = "Acceso denegado"): self
    {
        return new self(self::errorBody($message), 403);
    }

    public static function notFound(
        string $message = "Recurso no encontrado",
    ): self {
        return new self(self::errorBody($message), 404);
    }

    public static function serverError(string $message = "Error interno"): self
    {
        return new self(self::errorBody($message), 500);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;
        return new self($this->data, $this->status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header("Content-Type: application/json; charset=utf-8");

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->status !== 204) {
            echo json_encode(
                $this->data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        exit();
    }

    private static function errorBody(
        string $message,
        mixed $errors = null,
    ): array {
        $body = ["message" => $message];

        if ($errors !== null) {
            $body["errors"] = $errors;
        }

        return $body;
    }
}
