<?php

declare(strict_types=1);

namespace App\Modules\Sodexo\Domain\Repository;

use App\Modules\Sodexo\Domain\Entity\SodexoEncuesta;

interface SodexoEncuestaRepositoryInterface
{
    public function guardar(SodexoEncuesta $encuesta): bool;

    public function buscarPorUsuario(int $userId): ?SodexoEncuesta;

    /**
     * @return SodexoEncuesta[]
     */
    public function listarTodas(): array;
}
