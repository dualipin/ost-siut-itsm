<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\ListAnnualFinancialReportsUseCase;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
requireAnnualReportsAccess($container, true);

$reports = $container->get(ListAnnualFinancialReportsUseCase::class)->execute();

$renderer->render('./gestionar.latte', [
    'reports' => $reports,
    'message' => ($_GET['created'] ?? '') === '1' ? 'El informe se cargó correctamente.' : (($_GET['updated'] ?? '') === '1' ? 'El informe se actualizó correctamente.' : (($_GET['deleted'] ?? '') === '1' ? 'El informe se eliminó correctamente.' : null)),
    'error' => ($_GET['error'] ?? '') === 'notfound' ? 'El informe solicitado no existe.' : null,
]);