<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Provider\UserContextProvider;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userContextProvider = $container->get(UserContextProvider::class);

$user = $userContextProvider->get();

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . "/index.latte", [
    "user" => $user,
]);
