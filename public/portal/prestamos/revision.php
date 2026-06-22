<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . '/../../../bootstrap.php';

$container   = Bootstrap::buildContainer();
$middleware  = $container->get(MiddlewareFactory::class);
$runner      = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());
$runner->runOrRedirect($middleware->role(RoleEnum::Lider, RoleEnum::Finanzas, RoleEnum::Admin));

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$currentUser = $userContext->get();

$renderer->render(__DIR__ . '/revision.latte', [
    'user' => $currentUser,
]);
