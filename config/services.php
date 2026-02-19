<?php

use App\Module\Prestamo\Service\CalculadoraCompuesto;
use App\Module\Prestamo\Service\SimuladorService;
use function DI\autowire;

return function (\DI\ContainerBuilder $container) {
    $container->addDefinitions([
        SimuladorService::class => autowire(SimuladorService::class),
        CalculadoraCompuesto::class => autowire(CalculadoraCompuesto::class),
    ]);
};