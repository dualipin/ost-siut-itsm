<?php

namespace App\Modules\LaborUnion;

use App\Modules\ModuleInterface;
use DI\ContainerBuilder;

class LaborUnionModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([]);
    }
}
