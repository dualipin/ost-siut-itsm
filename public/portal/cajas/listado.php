<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetCategoriesListUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCashBoxesListUseCase;
use App\Modules\CashBoxes\Domain\DTO\CashBoxFilterCriteria;
use App\Modules\CashBoxes\Domain\Enum\BoxStatusEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

// Require authentication
$runner->runOrRedirect($middleware->auth());

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(GetCashBoxesListUseCase::class);
$categoriesUseCase = $container->get(GetCategoriesListUseCase::class);

// Build filter criteria from request parameters
$criteria = new CashBoxFilterCriteria(
    name: $_GET['name'] ?? null,
    status: isset($_GET['status']) && $_GET['status'] !== '' ? BoxStatusEnum::from($_GET['status']) : null,
    minInitialBalance: isset($_GET['min_initial']) && $_GET['min_initial'] !== '' ? (float)$_GET['min_initial'] : null,
    maxInitialBalance: isset($_GET['max_initial']) && $_GET['max_initial'] !== '' ? (float)$_GET['max_initial'] : null,
    minCurrentBalance: isset($_GET['min_current']) && $_GET['min_current'] !== '' ? (float)$_GET['min_current'] : null,
    maxCurrentBalance: isset($_GET['max_current']) && $_GET['max_current'] !== '' ? (float)$_GET['max_current'] : null,
    sortBy: $_GET['sort_by'] ?? 'created_at',
    sortOrder: $_GET['sort_order'] ?? 'DESC',
);

$data = $useCase->execute($criteria);
$data['categories'] = $categoriesUseCase->execute()['categories'];

// Pass filters to template for form preservation
$data['filters'] = [
    'name' => $_GET['name'] ?? '',
    'status' => $_GET['status'] ?? '',
    'min_initial' => $_GET['min_initial'] ?? '',
    'max_initial' => $_GET['max_initial'] ?? '',
    'min_current' => $_GET['min_current'] ?? '',
    'max_current' => $_GET['max_current'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_order' => $_GET['sort_order'] ?? 'DESC',
];

$renderer->render(__DIR__ . "/../../../templates/cajas/listado.latte", $data);
