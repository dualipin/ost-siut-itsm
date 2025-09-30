<?php

declare(strict_types=1);

namespace App\Entidades;

class PagoPrestamo
{
  public function __construct(
    public ?int $id = null,
    public int $numeroPago = 0,
    public float $montoPago = 0.0,
    public string $fechaProgramada = '',
    public ?string $fechaPago = null,
    public string $estado = 'pendiente',
    public int $fkSolicitud = 0
  ) {}

  public function estaVencido(): bool
  {
    if ($this->estado === 'pagado') {
      return false;
    }

    $fechaProgramada = new \DateTime($this->fechaProgramada);
    $hoy = new \DateTime();

    return $hoy > $fechaProgramada;
  }

  public function diasVencido(): int
  {
    if (!$this->estaVencido()) {
      return 0;
    }

    $fechaProgramada = new \DateTime($this->fechaProgramada);
    $hoy = new \DateTime();

    return $hoy->diff($fechaProgramada)->days;
  }
}
