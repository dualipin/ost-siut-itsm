<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\ListPublicFAQUseCase;

$container = Bootstrap::buildContainer();
$useCase = $container->get(ListPublicFAQUseCase::class);
$renderer = $container->get(RendererInterface::class);

$faqs = $useCase->execute();

$renderer->render("./preguntas-frecuentes.latte", [
    'faqs' => $faqs,
]);
