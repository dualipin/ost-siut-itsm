<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Config\AppConfig;
use App\Modules\CashBoxes\Application\UseCase\GetAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

requireAnnualReportsAccess($container, false);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

try {
    $report = $container->get(GetAnnualFinancialReportUseCase::class)->execute($id);
} catch (AnnualFinancialReportNotFoundException) {
    http_response_code(404);
    exit;
}

if (strtolower(pathinfo($report->document, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(415);
    echo 'El archivo no es un PDF visualizable.';
    exit;
}

$config = $container->get(AppConfig::class);
$filePath = resolveAnnualReportAbsolutePath($report->document, $config);

if ($filePath === null || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    echo 'El archivo no está disponible por el momento.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: private');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string) filesize($filePath));

readfile($filePath);
exit;