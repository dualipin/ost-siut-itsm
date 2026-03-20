<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\ListThreadsByTypeUseCase;
use App\Modules\Messaging\Domain\Enum\ThreadType;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(ListThreadsByTypeUseCase::class);

$threads = $useCase->execute(ThreadType::QA);

$renderer->render(__DIR__ . '/index.latte', [
    'threads' => $threads,
]);
