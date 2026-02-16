<?php

namespace App\Module\Sindicato\Entity;

final readonly class Colores
{
    public function __construct(
        public int $id,
        public string $primario,
        public string $secundario,
        public string $exito,
        public string $info,
        public string $advertencia,
        public string $peligro,
        public string $claro,
        public string $oscuro,
        public string $blanco,
        public string $cuerpo,
        public string $fondoCuerpo,
    ) {}
}
