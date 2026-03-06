<?php

namespace App\Module\Sindicato\Entity;

final readonly class Colores
{
    public int $id;
    public function __construct(
        public string $primario,
        public string $secundario,
        public string $exito,
        public string $info,
        public string $advertencia,
        public string $peligro,
        public string $claro,
        public string $oscuro,
        public string $blanco = "#ffffff",
        public string $cuerpo = "#212529",
        public string $fondoCuerpo = "#f8f9fa",
    ) {
        $this->id = 1;
    }
}
