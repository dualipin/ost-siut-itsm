<?php

namespace App\Modules;

use DI\ContainerBuilder;
use function DI\autowire;
abstract class AbstractModule implements ModuleInterface
{
    /**
     * @var array<class-string, class-string>
     */
    protected const array REPOSITORIES = [];

    /**
     * @var array<class-string>
     */
    protected const array SERVICES = [];

    /**
     * @var array<class-string>
     */
    protected const array USE_CASES = [];

    public function register(ContainerBuilder $container): void
    {
        $definitions = [];

        foreach (static::SERVICES as $service) {
            $definitions[$service] = autowire();
        }

        foreach (static::USE_CASES as $useCase) {
            $definitions[$useCase] = autowire();
        }

        foreach (static::REPOSITORIES as $interface => $implementation) {
            $definitions[$interface] = autowire($implementation);
        }

        $container->addDefinitions($definitions);
    }
}
