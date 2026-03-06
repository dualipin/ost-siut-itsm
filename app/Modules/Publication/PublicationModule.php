<?php

namespace App\Modules\Publication;

use App\Modules\ModuleInterface;
use DI\ContainerBuilder;

class PublicationModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([]);
    }
}
