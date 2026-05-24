<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\GetAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\UpdateAnnualFinancialReportUseCase;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportNotFoundException;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportValidationException;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportYearAlreadyExistsException;

require_once __DIR__ . "/../../../bootstrap.php";
require_once __DIR__ . '/helpers.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
requireAnnualReportsAccess($container, true);

$config = $container->get(AppConfig::class);
$getUseCase = $container->get(GetAnnualFinancialReportUseCase::class);
$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    header('Location: ./gestionar.php?error=notfound');
    exit;
}

try {
    $report = $getUseCase->execute($id);
} catch (AnnualFinancialReportNotFoundException) {
    header('Location: ./gestionar.php?error=notfound');
    exit;
}

$error = null;
$formData = [
    'id' => $report->id,
    'year' => $report->year,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = (int) ($_POST['year'] ?? 0);
    $documentPath = null;
    $currentDocument = $report->document;

    try {
        if (isset($_FILES['document']) && (int) ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $documentPath = storeAnnualReportDocument($_FILES['document'], $config, $year);
        } else {
            $documentPath = $currentDocument;
        }

        $updatedReport = $container->get(UpdateAnnualFinancialReportUseCase::class)->execute($id, $year, $documentPath);

        if ($documentPath !== $currentDocument) {
            deleteStoredAnnualReportDocument($currentDocument, $config);
        }

        header('Location: ./ver.php?id=' . $updatedReport->id . '&updated=1');
        exit;
    } catch (AnnualFinancialReportYearAlreadyExistsException | AnnualFinancialReportValidationException $exception) {
        if ($documentPath !== null && $documentPath !== $currentDocument) {
            deleteStoredAnnualReportDocument($documentPath, $config);
        }

        $error = $exception->getMessage();
        $formData = array_merge($formData, $_POST ?? []);
    } catch (Throwable) {
        if ($documentPath !== null && $documentPath !== $currentDocument) {
            deleteStoredAnnualReportDocument($documentPath, $config);
        }

        $error = 'Ocurrió un error inesperado al actualizar el informe.';
        $formData = array_merge($formData, $_POST ?? []);
    }
}

$renderer->render('./form.latte', [
    'title' => 'Editar informe financiero anual',
    'description' => 'Actualiza el año o reemplaza el archivo cargado.',
    'submitLabel' => 'Guardar cambios',
    'action' => './actualizar.php?id=' . $report->id,
    'report' => $report,
    'formData' => $formData,
    'error' => $error,
    'isEditMode' => true,
]);