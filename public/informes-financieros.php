<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\ListAnnualFinancialReportsUseCase;

require_once __DIR__ . '/../bootstrap.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$reports = $container->get(ListAnnualFinancialReportsUseCase::class)->execute();

$reportsByYear = [];

foreach ($reports as $report) {
	$reportsByYear[$report->year][] = $report;
}

krsort($reportsByYear);

$renderer->render('./informes-financieros.latte', [
	'reportsByYear' => $reportsByYear,
]);
