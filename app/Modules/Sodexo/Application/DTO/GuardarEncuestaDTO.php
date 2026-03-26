<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Application\DTO;

final readonly class GuardarEncuestaDTO
{
    public function __construct(
        public int $userId,
        public string $tipoEmpleado,

        // Administrativo - Diciembre 2025
        public ?float $admDicPuntualidad,
        public ?float $admDicAsistencia,

        // Administrativo - Enero 2026
        public ?float $admEnePuntualidad,
        public ?float $admEneAsistencia,

        // Administrativo - Febrero 2026
        public ?float $admFebPuntualidad,
        public ?float $admFebAsistencia,

        // Administrativo - Marzo 2026
        public ?float $admMarPuntualidad,
        public ?float $admMarAsistencia,

        // Docente - Diciembre 2025
        public bool $docDicPagado,

        // Docente - Marzo 2026
        public bool $docMarPagado,

        public ?string $firmaCurp,
    ) {}
}
