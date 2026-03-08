<?php

namespace App\Modules\Setting\Application\Command;

use App\Modules\Setting\Domain\Entity\Color;
use InvalidArgumentException;

final readonly class UpdateColorCommand
{
    private function __construct(
        public string $primary,
        public string $secondary,
        public string $success,
        public string $info,
        public string $warning,
        public string $danger,
        public string $light,
        public string $dark,
        public string $white,
        public string $body,
        public string $bodyBackground,
    ) {}

    public static function fromRequestPayload(array $payload): self
    {
        return new self(
            primary: self::normalize($payload, "primario"),
            secondary: self::normalize($payload, "secundario"),
            success: self::normalize($payload, "exito"),
            info: self::normalize($payload, "info"),
            warning: self::normalize($payload, "advertencia"),
            danger: self::normalize($payload, "peligro"),
            light: self::normalize($payload, "claro"),
            dark: self::normalize($payload, "oscuro"),
            white: self::normalize($payload, "blanco"),
            body: self::normalize($payload, "cuerpo"),
            bodyBackground: self::normalize($payload, "fondo_cuerpo"),
        );
    }

    public function toColor(): Color
    {
        return new Color(
            primary: $this->primary,
            secondary: $this->secondary,
            success: $this->success,
            info: $this->info,
            warning: $this->warning,
            danger: $this->danger,
            light: $this->light,
            dark: $this->dark,
            white: $this->white,
            body: $this->body,
            bodyBackground: $this->bodyBackground,
        );
    }

    private static function normalize(array $payload, string $field): string
    {
        $value = trim((string) ($payload[$field] ?? ""));

        if ($value === "") {
            throw new InvalidArgumentException("El campo '$field' es obligatorio.");
        }

        return strtolower($value);
    }
}
