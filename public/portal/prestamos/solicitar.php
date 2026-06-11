<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../../../bootstrap.php";


$container = Bootstrap::buildContainer();

// Auth
$runner = $container->get(MiddlewareRunner::class);
$middleware = $container->get(MiddlewareFactory::class);
$runner->runOrRedirect($middleware->auth());


$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);


$user = $userContext->get();

$renderer->render("./solicitar.latte", ["user" => $user]);