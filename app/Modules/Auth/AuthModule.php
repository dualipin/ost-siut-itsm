<?php

namespace App\Modules\Auth;

use App\Modules\ModuleInterface;
use DI\ContainerBuilder;

final class AuthModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([]);
    }
}
