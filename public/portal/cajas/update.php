<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\CashBoxes\Application\UseCase\UpdateCashBoxUseCase;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth([RoleEnum::Admin, RoleEnum::Finanzas, RoleEnum::Lider]));

$urlBuilder = $container->get(UrlBuilder::class);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $urlBuilder->to('/portal/cajas/listado.php'));
    exit;
}

$id = (int)($_POST['box_id'] ?? 0);
$name = $_POST['name'] ?? '';
$description = empty($_POST['description']) ? null : $_POST['description'];

try {
    $useCase = $container->get(UpdateCashBoxUseCase::class);
    $useCase->execute($id, $name, $description);
    
    header("Location: " . $urlBuilder->to('/portal/cajas/detalle.php', ['id' => $id, 'success' => 1]));
} catch (Exception $e) {
    header("Location: " . $urlBuilder->to('/portal/cajas/detalle.php', ['id' => $id, 'error' => $e->getMessage()]));
}
exit;
