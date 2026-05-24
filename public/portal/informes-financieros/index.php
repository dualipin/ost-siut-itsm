<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\ListAnnualFinancialReportsUseCase;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$user = requireAnnualReportsAccess($container, false);

$reports = $container->get(ListAnnualFinancialReportsUseCase::class)->execute();
$isPrivileged = in_array($user->role, [RoleEnum::Admin, RoleEnum::Lider], true);

$renderer->render('./index.latte', [
    'reports' => $reports,
    'isPrivileged' => $isPrivileged,
    'message' => isset($_GET['created']) || isset($_GET['updated']) ? 'Operación completada correctamente.' : null,
    'error' => ($_GET['error'] ?? '') === 'notfound' ? 'El informe solicitado no existe.' : null,
]);