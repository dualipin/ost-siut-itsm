<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetFinancialReportsListUseCase;
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
$useCase = $container->get(GetFinancialReportsListUseCase::class);

$boxId = isset($_GET['box_id']) && $_GET['box_id'] !== '' ? (int) $_GET['box_id'] : null;
$year = (int) ($_GET['year'] ?? date('Y'));

$data = $useCase->execute($boxId);
$data['defaultPeriodStart'] = sprintf('%d-01-01', $year);
$data['defaultPeriodEnd'] = sprintf('%d-12-31', $year);
$data['selectedYear'] = $year;

$renderer->render(__DIR__ . "/../../../templates/cajas/informe.latte", $data);
