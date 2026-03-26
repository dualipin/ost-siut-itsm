<?php

declare(strict_types=1);

namespace App\Modules\Sodexo;

use App\Modules\AbstractModule;
use App\Modules\Sodexo\Application\UseCase\GuardarEncuestaUseCase;
use App\Modules\Sodexo\Application\UseCase\ObtenerEncuestaUseCase;
use App\Modules\Sodexo\Application\UseCase\ObtenerTodasEncuestasUseCase;
use App\Modules\Sodexo\Domain\Repository\SodexoEncuestaRepositoryInterface;
use App\Modules\Sodexo\Infrastructure\Persistence\PdoSodexoEncuestaRepository;

final class SodexoModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        SodexoEncuestaRepositoryInterface::class => PdoSodexoEncuestaRepository::class,
    ];

    protected const array USE_CASES = [
        GuardarEncuestaUseCase::class,
        ObtenerEncuestaUseCase::class,
        ObtenerTodasEncuestasUseCase::class,
    ];
}
