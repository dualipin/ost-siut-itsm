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

$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$isPeriodic = (string) ($_POST['is_periodic'] ?? '1') === '1';
$active = isset($_POST['active']) && (string) $_POST['active'] === '1';

if ($name === '') {
    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['error' => 'El nombre es obligatorio.']));
    exit;
}

$frequencyDays = null;
$tentativePaymentMonth = null;
$tentativePaymentDay = null;

if ($isPeriodic) {
    $frequencyDays = max(1, (int) ($_POST['frequency_days'] ?? 15));
} else {
    $tentativePaymentMonth = max(1, min(12, (int) ($_POST['tentative_payment_month'] ?? 12)));
    $tentativePaymentDay = max(1, min(31, (int) ($_POST['tentative_payment_day'] ?? 1)));
}

$pdo = $container->get(PDO::class);

try {
    $statement = $pdo->prepare(
        'UPDATE cat_income_types
         SET name = :name,
             description = :description,
             is_periodic = :is_periodic,
             frequency_days = :frequency_days,
             tentative_payment_month = :tentative_payment_month,
             tentative_payment_day = :tentative_payment_day,
             active = :active
         WHERE income_type_id = :income_type_id'
    );

    $statement->execute([
        'income_type_id' => $incomeTypeId,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'is_periodic' => $isPeriodic ? 1 : 0,
        'frequency_days' => $frequencyDays,
        'tentative_payment_month' => $tentativePaymentMonth,
        'tentative_payment_day' => $tentativePaymentDay,
        'active' => $active ? 1 : 0,
    ]);

    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['success' => 1]));
} catch (Throwable $e) {
    header('Location: ' . $urlBuilder->to('/portal/prestamos/categorias.php', ['error' => $e->getMessage()]));
}

exit;