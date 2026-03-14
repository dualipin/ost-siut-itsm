<?php

use App\Bootstrap;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(RoleMiddleware::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware);

$renderer = $container->get(RendererInterface::class);

$data = [
    "avisos" => [],
];

$renderer->render("./avisos.latte", $data);
