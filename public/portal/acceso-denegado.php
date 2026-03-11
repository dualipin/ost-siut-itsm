<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Session\PhpSession;
use App\Infrastructure\Templating\RendererInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

// Iniciar sesión
$sessionManager = $container->get(PhpSession::class);
$sessionManager->start();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

http_response_code(403);

$renderer = $container->get(RendererInterface::class);
$renderer->render("./acceso-denegado.latte");
