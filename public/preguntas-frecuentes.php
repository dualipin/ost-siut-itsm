<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\DependencyInjection\ContainerFactory;
use App\Modules\Messaging\Application\UseCase\ListPublicFAQUseCase;

$container = ContainerFactory::create();
$useCase = $container->get(ListPublicFAQUseCase::class);

$faqs = $useCase->execute();

$servicioLatte = $container->get(\App\Infrastructure\Latte\ServicioLatte::class);
$servicioLatte->render(__DIR__ . '/preguntas-frecuentes.latte', [
    'faqs' => $faqs,
]);
