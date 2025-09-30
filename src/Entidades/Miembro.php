<?php

declare(strict_types=1);

namespace App\Entidades;

class Miembro
{
  public function __construct(
    public ?int $id = null,
    public string $nombre = '',
    public string $apellidos = '',
    public ?string $direccion = null,
    public ?string $telefono = null,
    public ?string $categoria = null,
    public ?string $departamento = null,
    public ?string $nss = null,
    public ?string $curp = null,
    public ?string $fechaIngreso = null,
    public ?string $fechaNacimiento = null,
    public ?float $salarioQuincenal = null,
    public ?int $fkUsuario = null
  ) {}

  public function getNombreCompleto(): string
  {
    return trim($this->nombre . ' ' . $this->apellidos);
  }

  public function tieneSalarioRegistrado(): bool
  {
    return $this->salarioQuincenal !== null && $this->salarioQuincenal > 0;
  }

  public function getMontoMaximoPrestamo(): float
  {
    if (!$this->tieneSalarioRegistrado()) {
      return 0.0;
    }

    return $this->salarioQuincenal * 0.70; // Máximo 70% del salario
  }
}
