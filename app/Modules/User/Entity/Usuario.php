<?php

namespace App\Module\Usuario\Entity;

use App\Module\Auth\Enum\RoleEnum;
use DateTimeImmutable;

final readonly class Usuario
{
    public function __construct(
        public string $nombre,
        public string $apellidos,
        public string $email,
        public string $passwordHash,
        public RoleEnum $rol,

        // optional / nullable fields (map to DB nullable columns)
        public bool $activo = true,
        public ?int $id = null,
        public ?string $curp = null,
        public ?DateTimeImmutable $fechaNacimiento = null,
        public ?string $direccion = null,
        public ?string $telefono = null,
        public ?string $foto = null,

        // banking
        public ?string $bancoNombre = null,
        public ?string $clabeInterbancaria = null,
        public ?string $cuentaBancaria = null,

        // laboral
        public ?string $categoria = null,
        public ?string $departamento = null,
        public ?string $nss = null,
        public float $salarioQuincenal = 0.0,
        public ?DateTimeImmutable $fechaIngresoLaboral = null,

        // session / soft-delete
        public ?DateTimeImmutable $ultimoIngreso = null,
        public ?DateTimeImmutable $fechaEliminacion = null,
        public ?DateTimeImmutable $fechaCreacion = null,
        public ?DateTimeImmutable $fechaActualizacion = null,
    ) {}
}
