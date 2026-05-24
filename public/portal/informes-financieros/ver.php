<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$user = requireAnnualReportsAccess($container, false);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ./index.php');
    exit;
}

try {
    $report = $container->get(GetAnnualFinancialReportUseCase::class)->execute($id);
} catch (AnnualFinancialReportNotFoundException) {
    header('Location: ./index.php?error=notfound');
    exit;
}

$renderer->render('./ver.latte', [
    'report' => $report,
    'isPrivileged' => in_array($user->role, [RoleEnum::Admin, RoleEnum::Lider], true),
    'created' => ($_GET['created'] ?? '') === '1',
    'updated' => ($_GET['updated'] ?? '') === '1',
]);