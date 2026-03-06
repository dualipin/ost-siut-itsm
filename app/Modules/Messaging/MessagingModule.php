<?php

namespace App\Modules\Messaging;

use App\Modules\ModuleInterface;
use DI\ContainerBuilder;

class MessagingModule implements ModuleInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->addDefinitions([]);
    }
}
