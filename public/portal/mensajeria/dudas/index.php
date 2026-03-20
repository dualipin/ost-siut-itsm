<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\ListThreadsByTypeUseCase;
use App\Modules\Messaging\Domain\Enum\ThreadType;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(ListThreadsByTypeUseCase::class);

$mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int)$_GET['mes'] : null;
$anio = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int)$_GET['anio'] : null;

$threads = $useCase->execute(ThreadType::QA, $mes, $anio);

$renderer->render(__DIR__ . '/index.latte', [
    'threads' => $threads,
    'filtros' => [
        'mes' => $mes,
        'anio' => $anio,
    ],
]);
