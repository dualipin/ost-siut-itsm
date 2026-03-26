<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Application\UseCase;

use App\Modules\Sodexo\Domain\Entity\SodexoEncuesta;
use App\Modules\Sodexo\Domain\Repository\SodexoEncuestaRepositoryInterface;

final readonly class ObtenerTodasEncuestasUseCase
{
    public function __construct(
        private SodexoEncuestaRepositoryInterface $repository,
    ) {}

    /**
     * Retorna todas las encuestas registradas (para el reporte del administrador).
     *
     * @return SodexoEncuesta[]
     */
    public function execute(): array
    {
        return $this->repository->listarTodas();
    }
}
