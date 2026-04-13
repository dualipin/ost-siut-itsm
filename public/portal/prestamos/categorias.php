<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
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
$pdo = $container->get(PDO::class);

$filters = [
    'name' => trim((string) ($_GET['name'] ?? '')),
    'type' => (string) ($_GET['type'] ?? ''),
    'status' => (string) ($_GET['status'] ?? 'all'),
];

$conditions = [];
$params = [];

if ($filters['name'] !== '') {
    $conditions[] = 'c.name LIKE :name';
    $params['name'] = '%' . $filters['name'] . '%';
}

if ($filters['type'] === 'periodic') {
    $conditions[] = 'c.is_periodic = 1';
} elseif ($filters['type'] === 'non_periodic') {
    $conditions[] = 'c.is_periodic = 0';
}

if ($filters['status'] === 'active') {
    $conditions[] = 'c.active = 1';
} elseif ($filters['status'] === 'inactive') {
    $conditions[] = 'c.active = 0';
}

$sql = <<<SQL
SELECT
    c.income_type_id,
    c.name,
    c.description,
    c.is_periodic,
    c.frequency_days,
    c.tentative_payment_month,
    c.tentative_payment_day,
    c.active,
    COALESCE(pc.payment_config_count, 0) AS payment_config_count,
    COALESCE(la.amortization_count, 0) AS amortization_count
FROM cat_income_types c
LEFT JOIN (
    SELECT income_type_id, COUNT(*) AS payment_config_count
    FROM loan_payment_configuration
    GROUP BY income_type_id
) pc ON pc.income_type_id = c.income_type_id
LEFT JOIN (
    SELECT income_type_id, COUNT(*) AS amortization_count
    FROM loan_amortization
    GROUP BY income_type_id
) la ON la.income_type_id = c.income_type_id
SQL;

if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY c.name ASC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$incomeTypes = $statement->fetchAll(PDO::FETCH_OBJ);

$renderer->render(__DIR__ . '/../../../templates/prestamos/categorias.latte', [
    'incomeTypes' => $incomeTypes,
    'filters' => $filters,
    'months' => [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ],
    'success' => isset($_GET['success']),
    'error' => isset($_GET['error']) ? (string) $_GET['error'] : null,
]);