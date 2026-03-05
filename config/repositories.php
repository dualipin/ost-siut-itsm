<?php

use App\Module\Mensajeria\Repository\MensajeRepository;
use DI\ContainerBuilder;

use function DI\autowire;

return function (ContainerBuilder $container) {
    $container->addDefinitions([
        MensajeRepository::class => autowire(MensajeRepository::class),
    ]);
};
