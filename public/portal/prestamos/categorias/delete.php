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
    $referencesStatement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM loan_payment_configuration WHERE income_type_id = :income_type_id) AS payment_config_count,
            (SELECT COUNT(*) FROM loan_amortization WHERE income_type_id = :income_type_id) AS amortization_count'
    );
    $referencesStatement->execute(['income_type_id' => $incomeTypeId]);
    $references = $referencesStatement->fetch(PDO::FETCH_OBJ);

    $hasReferences = $references !== false
        && ((int) $references->payment_config_count > 0 || (int) $references->amortization_count > 0);

    if ($hasReferences) {
        $statement = $pdo->prepare('UPDATE cat_income_types SET active = 0 WHERE income_type_id = :income_type_id');
        $statement->execute(['income_type_id' => $incomeTypeId]);
    } else {
        $statement = $pdo->prepare('DELETE FROM cat_income_types WHERE income_type_id = :income_type_id');
        $statement->execute(['income_type_id' => $incomeTypeId]);
    }

    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['success' => 1]));
} catch (Throwable $e) {
    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['error' => $e->getMessage()]));
}

exit;