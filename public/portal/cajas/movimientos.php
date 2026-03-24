<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetTransactionsViewDataUseCase;
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
$useCase = $container->get(GetTransactionsViewDataUseCase::class);

$boxId = isset($_GET['box_id']) ? (int)$_GET['box_id'] : null;
$type = $_GET['type'] ?? null;
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$data = $useCase->execute($boxId, $type, $categoryId, $startDate, $endDate);

$renderer->render(__DIR__ . "/../../../templates/cajas/movimientos.latte", $data);
