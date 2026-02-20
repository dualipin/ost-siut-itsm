<?php

use App\Module\Auth\Service\AuthenticationService;
use App\Module\Auth\Service\RoleService;
use App\Module\Auth\Session\SessionManager;
use App\Module\Auth\Middleware\MiddlewareFactory;
use App\Module\Prestamo\Service\CalculadoraCompuesto;
use App\Module\Prestamo\Service\SimuladorService;
use function DI\autowire;

return function (\DI\ContainerBuilder $container) {
    $container->addDefinitions([
        // Auth Services
        AuthenticationService::class => autowire(AuthenticationService::class),
        RoleService::class => autowire(RoleService::class),
        SessionManager::class => function () {
            return new SessionManager(3600); // 1 hora
        },
        MiddlewareFactory::class => autowire(MiddlewareFactory::class),

        // Prestamo Services
        SimuladorService::class => autowire(SimuladorService::class),
        CalculadoraCompuesto::class => autowire(CalculadoraCompuesto::class),
    ]);
};