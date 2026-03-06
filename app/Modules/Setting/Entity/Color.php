<?php

namespace App\Modules\Setting\Entity;

final readonly class Color
{
    public int $id;
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
        $this->id = 1;
    }
}
