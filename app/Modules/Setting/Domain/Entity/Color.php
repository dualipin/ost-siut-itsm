<?php

namespace App\Modules\Setting\Domain\Entity;

use InvalidArgumentException;

final readonly class Color
{
    public function __construct(
        public string $primary,
        public string $secondary,
        public string $success,
        public string $info,
        public string $warning,
        public string $danger,
        public string $light,
        public string $dark,
        public string $white = "#ffffff",
        public string $body = "#212529",
        public string $bodyBackground = "#f8f9fa",
    ) {
        self::assertHex($this->primary, "primary");
        self::assertHex($this->secondary, "secondary");
        self::assertHex($this->success, "success");
        self::assertHex($this->info, "info");
        self::assertHex($this->warning, "warning");
        self::assertHex($this->danger, "danger");
        self::assertHex($this->light, "light");
        self::assertHex($this->dark, "dark");
        self::assertHex($this->white, "white");
        self::assertHex($this->body, "body");
        self::assertHex($this->bodyBackground, "bodyBackground");
    }

    private static function assertHex(string $value, string $field): void
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            throw new InvalidArgumentException(
                "El campo '$field' debe ser un color hexadecimal válido (ej: #ff0000).",
            );
        }
    }
}
