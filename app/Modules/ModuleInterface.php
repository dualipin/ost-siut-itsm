<?php

namespace App\Modules;

use DI\ContainerBuilder;

interface ModuleInterface
{
    public function register(ContainerBuilder $container): void;
}
