<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetCategoriesListUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCashBoxesListUseCase;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

// Only authorized roles
$runner->runOrRedirect($middleware->auth([RoleEnum::Admin, RoleEnum::Lider, RoleEnum::Finanzas]));

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(GetCashBoxesListUseCase::class);
$categoriesUseCase = $container->get(GetCategoriesListUseCase::class);

$data = $useCase->execute();
$data['categories'] = $categoriesUseCase->execute()['categories'];

$renderer->render(__DIR__ . "/../../../templates/cajas/listado.latte", $data);
