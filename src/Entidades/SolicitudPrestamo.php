<?php

declare(strict_types=1);

namespace App\Entidades;

class SolicitudPrestamo
{
  public function __construct(
    public ?int $id = null,
    public float $montoSolicitado = 0.0,
    public ?float $montoAprobado = null,
    public int $plazoMeses = 0,
    public string $tipoDescuento = 'quincenal',
    public string $justificacion = '',
    public string $reciboNomina = '',
    public string $estado = 'pendiente',
    public string $fechaSolicitud = '',
    public ?string $fechaRespuesta = null,
    public ?string $motivoRechazo = null,
    public ?string $pagareFirmado = null,
    public ?string $fechaPagare = null,
    public float $tasaInteres = 0.0,
    public int $fkMiembro = 0,
    public ?int $fkAprobador = null,
    public ?Miembro $miembro = null,
    public ?Miembro $aprobador = null,
    public array $pagos = []
  ) {}

  public function calcularMontoMaximo(float $salarioQuincenal): float
  {
    // Máximo 70% del salario quincenal
    return $salarioQuincenal * 0.70;
  }

  public function generarCorridaFinanciera(): array
  {
    if (!$this->montoAprobado || $this->plazoMeses <= 0) {
      return [];
    }

    $corrida = [];
    $montoConInteres = $this->montoAprobado * (1 + ($this->tasaInteres / 100));

    if ($this->tipoDescuento === 'quincenal') {
      $pagosQuincenales = $this->plazoMeses * 2; // 2 quincenas por mes
      $montoPorPago = $montoConInteres / $pagosQuincenales;

      $fechaInicio = new \DateTime();
      for ($i = 1; $i <= $pagosQuincenales; $i++) {
        $fechaPago = clone $fechaInicio;
        $fechaPago->add(new \DateInterval('P' . ($i * 15) . 'D'));

        $corrida[] = [
          'numero' => $i,
          'fecha' => $fechaPago->format('Y-m-d'),
          'monto' => round($montoPorPago, 2),
          'saldo' => round($montoConInteres - ($montoPorPago * $i), 2)
        ];
      }
    } else {
      // Para aguinaldo o prima vacacional, mostrar pagos quincenales hasta la fecha objetivo
      $fechaObjetivo = $this->calcularFechaObjetivo();
      $diasHastaObjetivo = (new \DateTime())->diff($fechaObjetivo)->days;
      $pagosQuincenales = ceil($diasHastaObjetivo / 15);

      if ($pagosQuincenales > 0) {
        $montoPorPago = $montoConInteres / $pagosQuincenales;

        $fechaInicio = new \DateTime();
        for ($i = 1; $i <= $pagosQuincenales; $i++) {
          $fechaPago = clone $fechaInicio;
          $fechaPago->add(new \DateInterval('P' . ($i * 15) . 'D'));

          $corrida[] = [
            'numero' => $i,
            'fecha' => $fechaPago->format('Y-m-d'),
            'monto' => round($montoPorPago, 2),
            'saldo' => round($montoConInteres - ($montoPorPago * $i), 2)
          ];
        }
      }
    }

    return $corrida;
  }

  private function calcularFechaObjetivo(): \DateTime
  {
    $ahora = new \DateTime();
    $año = (int)$ahora->format('Y');

    return match ($this->tipoDescuento) {
      'aguinaldo' => new \DateTime($año . '-12-15'),
      'prima_vacacional' => new \DateTime(($año + 1) . '-07-15'),
      default => $ahora->add(new \DateInterval('P' . $this->plazoMeses . 'M'))
    };
  }

  public function puedeSerAprobada(float $salarioQuincenal, array $prestamosActivos): bool
  {
    // Verificar que no exceda el 70% del salario
    $montoMaximo = $this->calcularMontoMaximo($salarioQuincenal);

    // Sumar préstamos activos
    $montoActivo = array_sum(array_column($prestamosActivos, 'monto_aprobado'));

    return ($montoActivo + $this->montoSolicitado) <= $montoMaximo;
  }
}
