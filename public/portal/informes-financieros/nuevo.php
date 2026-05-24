<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\CreateAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportValidationException;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportYearAlreadyExistsException;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
requireAnnualReportsAccess($container, true);
$config = $container->get(AppConfig::class);

$error = null;
$formData = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = (int) ($_POST['year'] ?? 0);

    try {
        $documentPath = storeAnnualReportDocument($_FILES['document'] ?? [], $config, $year);
        $report = $container->get(CreateAnnualFinancialReportUseCase::class)->execute($year, $documentPath);

        header('Location: ./ver.php?id=' . $report->id . '&created=1');
        exit;
    } catch (AnnualFinancialReportYearAlreadyExistsException | AnnualFinancialReportValidationException $exception) {
        if (isset($documentPath)) {
            deleteStoredAnnualReportDocument($documentPath, $config);
        }

        $error = $exception->getMessage();
    } catch (Throwable) {
        if (isset($documentPath)) {
            deleteStoredAnnualReportDocument($documentPath, $config);
        }

        $error = 'Ocurrió un error inesperado al guardar el informe.';
    }
}

$renderer->render('./form.latte', [
    'title' => 'Nuevo informe financiero anual',
    'description' => 'Carga el archivo oficial del informe por año.',
    'submitLabel' => 'Guardar informe',
    'action' => './nuevo.php',
    'report' => null,
    'formData' => $formData,
    'error' => $error,
    'isEditMode' => false,
]);