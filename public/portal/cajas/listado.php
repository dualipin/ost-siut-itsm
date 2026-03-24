<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetCategoriesListUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCashBoxesListUseCase;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(GetCashBoxesListUseCase::class);
$categoriesUseCase = $container->get(GetCategoriesListUseCase::class);

$name = $_GET['name'] ?? null;
$status = $_GET['status'] ?? null;
$minInitial = isset($_GET['min_initial']) && $_GET['min_initial'] !== '' ? (float)$_GET['min_initial'] : null;
$maxInitial = isset($_GET['max_initial']) && $_GET['max_initial'] !== '' ? (float)$_GET['max_initial'] : null;
$minCurrent = isset($_GET['min_current']) && $_GET['min_current'] !== '' ? (float)$_GET['min_current'] : null;
$maxCurrent = isset($_GET['max_current']) && $_GET['max_current'] !== '' ? (float)$_GET['max_current'] : null;
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

$data = $useCase->execute($name, $status, $minInitial, $maxInitial, $minCurrent, $maxCurrent, $sortBy, $sortOrder);
$data['categories'] = $categoriesUseCase->execute()['categories'];

$renderer->render(__DIR__ . "/../../../templates/cajas/listado.latte", $data);
