<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Domain\Entity;

use DateTimeImmutable;

final readonly class SodexoEncuesta
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $tipoEmpleado, // 'administrativo' | 'docente'

        // Datos del usuario (cargados desde JOIN con users)
        public string $userName     = '',
        public string $userSurnames = '',
        public string $userCategory = '',
        public string $userDepartment = '',

        // Administrativo: monto pagado por mes (NULL = mes no seleccionado / no pagado)
        public ?float $admDicPuntualidad = null,
        public ?float $admDicAsistencia  = null,
        public ?float $admEnePuntualidad = null,
        public ?float $admEneAsistencia  = null,
        public ?float $admFebPuntualidad = null,
        public ?float $admFebAsistencia  = null,
        public ?float $admMarPuntualidad = null,
        public ?float $admMarAsistencia  = null,

        public ?string $admDicRecibo = null,
        public ?string $admEneRecibo = null,
        public ?string $admFebRecibo = null,
        public ?string $admMarRecibo = null,

        // Docente: true = se le pagó ese mes (100 pesos cada uno)
        public bool $docDicPagado = false,
        public bool $docMarPagado = false,

        public ?string $docDicRecibo = null,
        public ?string $docMarRecibo = null,

        // Firma: CURP del agremiado
        public ?string $firmaCurp = null,

        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    // ------------------------------------------------------------------ //
    //  Helpers de cálculo                                                 //
    // ------------------------------------------------------------------ //

    public function esAdministrativo(): bool
    {
        return $this->tipoEmpleado === 'administrativo';
    }

    public function esDocente(): bool
    {
        return $this->tipoEmpleado === 'docente';
    }

    /**
     * Total máximo que debió pagársele (según su tipo).
     */
    public function totalEsperado(): float
    {
        return $this->esDocente() ? 200.0 : 400.0;
    }

    /**
     * Total que SÍ le pagaron de acuerdo con lo capturado en la encuesta.
     */
    public function totalPagado(): float
    {
        if ($this->esDocente()) {
            return ($this->docDicPagado ? 100.0 : 0.0)
                 + ($this->docMarPagado ? 100.0 : 0.0);
        }

        // Administrativo: suma puntualidad + asistencia de cada mes seleccionado
        $meses = [
            [$this->admDicPuntualidad, $this->admDicAsistencia],
            [$this->admEnePuntualidad, $this->admEneAsistencia],
            [$this->admFebPuntualidad, $this->admFebAsistencia],
            [$this->admMarPuntualidad, $this->admMarAsistencia],
        ];

        $total = 0.0;
        foreach ($meses as [$puntualidad, $asistencia]) {
            // Un mes está "seleccionado" cuando al menos uno de los dos campos no es NULL
            if ($puntualidad !== null || $asistencia !== null) {
                $total += ($puntualidad ?? 0.0) + ($asistencia ?? 0.0);
            }
        }

        return $total;
    }

    /**
     * Lo que todavía se le debe al agremiado.
     */
    public function totalAdeudo(): float
    {
        return max(0.0, $this->totalEsperado() - $this->totalPagado());
    }

    // ------------------------------------------------------------------ //
    //  Helpers por mes (administrativos)                                  //
    // ------------------------------------------------------------------ //

    /** ¿El mes fue marcado como "pagado" en la encuesta? */
    public function mesAdmSeleccionado(string $mes): bool
    {
        return match ($mes) {
            'dic' => $this->admDicPuntualidad !== null || $this->admDicAsistencia !== null,
            'ene' => $this->admEnePuntualidad !== null || $this->admEneAsistencia !== null,
            'feb' => $this->admFebPuntualidad !== null || $this->admFebAsistencia !== null,
            'mar' => $this->admMarPuntualidad !== null || $this->admMarAsistencia !== null,
            default => false,
        };
    }

    /** Total pagado en un mes específico (administrativo). */
    public function pagadoEnMes(string $mes): float
    {
        return match ($mes) {
            'dic' => ($this->admDicPuntualidad ?? 0.0) + ($this->admDicAsistencia ?? 0.0),
            'ene' => ($this->admEnePuntualidad ?? 0.0) + ($this->admEneAsistencia ?? 0.0),
            'feb' => ($this->admFebPuntualidad ?? 0.0) + ($this->admFebAsistencia ?? 0.0),
            'mar' => ($this->admMarPuntualidad ?? 0.0) + ($this->admMarAsistencia ?? 0.0),
            default => 0.0,
        };
    }
}
