<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\CashBoxes\Application\UseCase\CreateCashBoxUseCase;
use App\Shared\Context\UserContextInterface;
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

$userContext = $container->get(UserContextInterface::class);
$user = $userContext->get();
if (!$user) {
    header("Location: " . $urlBuilder->to('/portal/auth/login.php'));
    exit;
}

$name = $_POST['name'] ?? '';
$description = empty($_POST['description']) ? null : $_POST['description'];
$currency = $_POST['currency'] ?? 'MXN';
$initialBalance = (float)($_POST['initial_balance'] ?? 0.0);

try {
    $useCase = $container->get(CreateCashBoxUseCase::class);
    $useCase->execute($user->id, $name, $description, $currency, $initialBalance);
    
    header("Location: " . $urlBuilder->to('/portal/cajas/listado.php', ['success' => 1]));
} catch (Exception $e) {
    header("Location: " . $urlBuilder->to('/portal/cajas/listado.php', ['error' => $e->getMessage()]));
}
exit;
