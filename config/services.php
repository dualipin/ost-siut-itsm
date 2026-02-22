<?php

use App\Http\Middleware\MiddlewareFactory;
use App\Infrastructure\Security\CsrfTokenManager;
use App\Infrastructure\Session\SessionManager;
use App\Module\Auth\Service\AuthenticationService;
use App\Module\Prestamo\Service\CalculadoraCompuesto;
use App\Module\Prestamo\Service\SimuladorService;
use App\Shared\Utils\AuthHelper;

use function DI\autowire;

return function (\DI\ContainerBuilder $container) {
    $container->addDefinitions([
        // Session & Security
        SessionManager::class => fn() => new SessionManager(),
        CsrfTokenManager::class => autowire(CsrfTokenManager::class),

        // Auth Services & Helpers
        AuthenticationService::class => autowire(AuthenticationService::class),
        AuthHelper::class => autowire(AuthHelper::class),
        MiddlewareFactory::class => autowire(MiddlewareFactory::class),

        // Prestamo Services
        SimuladorService::class => autowire(SimuladorService::class),
        CalculadoraCompuesto::class => autowire(CalculadoraCompuesto::class),
    ]);
};
