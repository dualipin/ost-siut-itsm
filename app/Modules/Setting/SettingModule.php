<?php

namespace App\Modules\Setting;

use App\Modules\AbstractModule;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\Setting\Application\UseCase\ResetColorUseCase;
use App\Modules\Setting\Application\UseCase\UpdateColorUseCase;
use App\Modules\Setting\Domain\Repository\SettingRepositoryInterface;
use App\Modules\Setting\Infrastructure\Persistence\PdoSettingRepository;

final class SettingModule extends AbstractModule
{
    protected const array REPOSITORIES = [
        SettingRepositoryInterface::class => PdoSettingRepository::class,
    ];

    protected const array USE_CASES = [
        GetColorUseCase::class,
        UpdateColorUseCase::class,
        ResetColorUseCase::class,
    ];
}
