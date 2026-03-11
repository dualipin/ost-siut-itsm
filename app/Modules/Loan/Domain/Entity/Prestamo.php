<?php

namespace App\Module\Prestamo\Entity;

final readonly class Prestamo
{
    public function __construct(public ?int $id = null, public ?string $s) {}
}
