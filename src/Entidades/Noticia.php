<?php

namespace App\Entidades;

class Noticia
{
    function __construct(
            private readonly ?int   $id,
            public readonly string  $titulo,
            public readonly string  $contenido,
            public readonly ?string $imagen,
            public readonly bool    $importante = false,
            public readonly string  $fecha,
            public readonly int     $idMiembro
    )
    {
    }

    function getId(): int
    {
        return $this->id;
    }
}
