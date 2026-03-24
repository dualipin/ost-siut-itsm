<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetCategoriesListUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetCashBoxDetailUseCase;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth([RoleEnum::Admin, RoleEnum::Lider, RoleEnum::Finanzas]));

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(GetCashBoxDetailUseCase::class);
$categoriesUseCase = $container->get(GetCategoriesListUseCase::class);
$urlBuilder = $container->get(UrlBuilder::class);

$boxId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $data = $useCase->execute($boxId);
    $data['categories'] = $categoriesUseCase->execute()['categories'];
    $renderer->render(__DIR__ . "/../../../templates/cajas/detalle.latte", $data);
} catch (Exception $e) {
    header("Location: " . $urlBuilder->to('/portal/cajas/listado.php'));
    exit;
}
