<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Requests\Application\UseCase\GetMyRequestsUseCase;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$useCase     = $container->get(GetMyRequestsUseCase::class);
$authUser    = $userContext->getUser();

$data             = $useCase->execute($authUser->userId);
$data['authUser'] = $authUser;

$renderer->render(__DIR__ . '/../../../templates/solicitudes/mis-solicitudes.latte', $data);
