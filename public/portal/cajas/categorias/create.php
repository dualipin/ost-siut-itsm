<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\CashBoxes\Application\UseCase\CreateCategoryUseCase;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth([RoleEnum::Admin, RoleEnum::Finanzas]));

$urlBuilder = $container->get(UrlBuilder::class);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $urlBuilder->to('/portal/cajas/categorias.php'));
    exit;
}

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? null;
$type = $_POST['type'] ?? 'expense';
$active = isset($_POST['active']) && $_POST['active'] === '1';

try {
    $useCase = $container->get(CreateCategoryUseCase::class);
    $useCase->execute($name, $description, $type, $active);
    
    header("Location: " . $urlBuilder->to('/portal/cajas/categorias.php', ['success' => 1]));
} catch (Exception $e) {
    header("Location: " . $urlBuilder->to('/portal/cajas/categorias.php', ['error' => $e->getMessage()]));
}
exit;
