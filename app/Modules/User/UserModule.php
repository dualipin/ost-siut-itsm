<?php

namespace App\Modules\User;

use App\Modules\ModuleInterface;
use DI\ContainerBuilder;

final class UserModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([]);
    }
}
