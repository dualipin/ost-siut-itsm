<?php

namespace App\Module\Publicacion\Entity;

use App\Module\Publicacion\Enum\TipoPublicacionEnum;
use DateTimeImmutable;

final readonly class Publicacion
{
    public function __construct(
        public string $titulo,
        public string $contenido,
        public array $imagenes,
        public DateTimeImmutable $fechaPublicacion,
        public TipoPublicacionEnum $tipo,
        public ?array $adjuntos = null,
        public ?DateTimeImmutable $fechaExpiracion = null,
        public ?string $resumen = null,
        public ?int $id = null,
    ) {}
}
