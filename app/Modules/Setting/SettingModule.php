<?php

namespace App\Modules\Setting;

use App\Modules\ModuleInterface;
use App\Modules\Setting\Repository\SettingRepository;
use App\Modules\Setting\Repository\SettingRepositoryInterface;
use DI\ContainerBuilder;

use function DI\autowire;

class SettingModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([
            SettingRepositoryInterface::class => autowire(
                SettingRepository::class,
            ),
        ]);
    }
}
