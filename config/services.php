<?php

use App\Http\Middleware\MiddlewareFactory;
use App\Infrastructure\Logging\FileLogger;
use App\Infrastructure\Security\CsrfTokenManager;
use App\Infrastructure\Session\PhpSession;
use App\Module\Auth\Service\AuthenticationService;
use App\Module\Mensajeria\Service\ContactoGeneralService;
use App\Module\Prestamo\Service\CalculadoraCompuesto;
use App\Module\Prestamo\Service\SimuladorService;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;

use function DI\autowire;

return function (ContainerBuilder $container) {
    $container->addDefinitions([
        // Session & Security
        PhpSession::class => fn() => new PhpSession(),
        CsrfTokenManager::class => autowire(CsrfTokenManager::class),

        // Logging
        LoggerInterface::class => fn() => new FileLogger(),

        // Auth Services & Helpers
        AuthenticationService::class => autowire(AuthenticationService::class),
        MiddlewareFactory::class => autowire(MiddlewareFactory::class),

        // Mensajeria Services
        ContactoGeneralService::class => autowire(
            ContactoGeneralService::class,
        ),

        // Prestamo Services
        SimuladorService::class => autowire(SimuladorService::class),
        CalculadoraCompuesto::class => autowire(CalculadoraCompuesto::class),
    ]);
};
