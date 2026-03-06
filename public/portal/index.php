<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Session\PhpSession;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Provider\UserContextProvider;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

// Iniciar sesión
$sessionManager = $container->get(PhpSession::class);
$sessionManager->start();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

// Obtener usuario autenticado
$userProvider = $container->get(UserContextProvider::class);
$usuario = $userProvider->get();

$renderer = $container->get(RendererInterface::class);
$renderer->render("./index.latte", [
    "usuario" => $usuario,
]);
