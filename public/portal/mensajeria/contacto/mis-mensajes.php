<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\ListThreadsByTypeUseCase;
use App\Modules\Messaging\Domain\Enum\ThreadType;
use App\Shared\Context\UserProviderInterface;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(ListThreadsByTypeUseCase::class);

$threads = $useCase->execute(ThreadType::Contact, null, null, $user->id);

$renderer->render(__DIR__ . '/mis-mensajes.latte', [
    'threads' => $threads,
]);
