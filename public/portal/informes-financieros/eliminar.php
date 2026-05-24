<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Config\AppConfig;
use App\Modules\CashBoxes\Application\UseCase\DeleteAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\GetAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

requireAnnualReportsAccess($container, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./gestionar.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ./gestionar.php?error=notfound');
    exit;
}

$getUseCase = $container->get(GetAnnualFinancialReportUseCase::class);
$config = $container->get(AppConfig::class);

try {
    $report = $getUseCase->execute($id);
    $container->get(DeleteAnnualFinancialReportUseCase::class)->execute($id);
    deleteStoredAnnualReportDocument($report->document, $config);

    header('Location: ./gestionar.php?deleted=1');
    exit;
} catch (AnnualFinancialReportNotFoundException) {
    header('Location: ./gestionar.php?error=notfound');
    exit;
} catch (Throwable) {
    header('Location: ./gestionar.php?error=1');
    exit;
}