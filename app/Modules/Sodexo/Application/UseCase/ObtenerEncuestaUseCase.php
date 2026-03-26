<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Application\UseCase;

use App\Modules\Sodexo\Domain\Entity\SodexoEncuesta;
use App\Modules\Sodexo\Domain\Repository\SodexoEncuestaRepositoryInterface;

final readonly class ObtenerEncuestaUseCase
{
    public function __construct(
        private SodexoEncuestaRepositoryInterface $repository,
    ) {}

    public function execute(int $userId): ?SodexoEncuesta
    {
        return $this->repository->buscarPorUsuario($userId);
    }
}
