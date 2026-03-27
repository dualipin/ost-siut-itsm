<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Application\UseCase;

use App\Modules\Sodexo\Application\DTO\GuardarEncuestaDTO;
use App\Modules\Sodexo\Domain\Entity\SodexoEncuesta;
use App\Modules\Sodexo\Domain\Repository\SodexoEncuestaRepositoryInterface;

final readonly class GuardarEncuestaUseCase
{
    public function __construct(
        private SodexoEncuestaRepositoryInterface $repository,
    ) {}

    public function execute(GuardarEncuestaDTO $dto): bool
    {
        $encuesta = new SodexoEncuesta(
            id: 0,
            userId: $dto->userId,
            tipoEmpleado: $dto->tipoEmpleado,

            admDicPuntualidad: $dto->admDicPuntualidad,
            admDicAsistencia:  $dto->admDicAsistencia,
            admEnePuntualidad: $dto->admEnePuntualidad,
            admEneAsistencia:  $dto->admEneAsistencia,
            admFebPuntualidad: $dto->admFebPuntualidad,
            admFebAsistencia:  $dto->admFebAsistencia,
            admMarPuntualidad: $dto->admMarPuntualidad,
            admMarAsistencia:  $dto->admMarAsistencia,

            admDicRecibo: $dto->admDicRecibo,
            admEneRecibo: $dto->admEneRecibo,
            admFebRecibo: $dto->admFebRecibo,
            admMarRecibo: $dto->admMarRecibo,

            docDicPagado: $dto->docDicPagado,
            docMarPagado: $dto->docMarPagado,

            docDicRecibo: $dto->docDicRecibo,
            docMarRecibo: $dto->docMarRecibo,

            firmaCurp: $dto->firmaCurp,
        );

        return $this->repository->guardar($encuesta);
    }
}
