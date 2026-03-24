<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetCategoriesListUseCase;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$urlBuilder = $container->get(UrlBuilder::class);
$authUser = $container->get(UserProviderInterface::class)->get();
if ($authUser === null || !in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider, RoleEnum::Finanzas], true)) {
	header('Location: ' . $urlBuilder->to('/portal/index.php'));
	exit;
}

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(GetCategoriesListUseCase::class);

$name = $_GET['name'] ?? null;
$type = $_GET['type'] ?? null;
$status = $_GET['status'] ?? null;
$contributionCategory = $_GET['contribution_category'] ?? null;
$sortBy = $_GET['sortBy'] ?? 'name';
$sortOrder = $_GET['sortOrder'] ?? 'ASC';

$data = $useCase->execute($name, $type, $status, $contributionCategory, $sortBy, $sortOrder);

$renderer->render(__DIR__ . "/../../../templates/cajas/categorias.latte", $data);
