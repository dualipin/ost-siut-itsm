<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$urlBuilder = $container->get(UrlBuilder::class);

$runner->runOrRedirect($middleware->auth());

$authUser = $container->get(UserProviderInterface::class)->get();
if ($authUser === null || !in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider, RoleEnum::Finanzas], true)) {
    header('Location: ' . $urlBuilder->to('/portal/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php'));
    exit;
}

$incomeTypeId = (int) ($_POST['income_type_id'] ?? 0);
if ($incomeTypeId <= 0) {
    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['error' => 'ID de categoría inválido.']));
    exit;
}

$pdo = $container->get(PDO::class);

try {
    $statement = $pdo->prepare('UPDATE cat_income_types SET active = 0 WHERE income_type_id = :income_type_id');
    $statement->execute(['income_type_id' => $incomeTypeId]);

    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['success' => 1]));
} catch (Throwable $e) {
    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['error' => $e->getMessage()]));
}

exit;