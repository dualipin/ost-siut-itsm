<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Modules\Sodexo\Application\UseCase\ObtenerTodasEncuestasUseCase;
use App\Shared\Context\UserContext;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

/** @var UserContext $userContext */
$userContext = $container->get(UserContext::class);
$authUser    = $userContext->get();

// Solo administradores y líderes pueden descargar los recibos
if ($authUser === null) {
    header("Location: /cuentas/login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

if (!in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider], true)) {
    header("Location: /portal/acceso-denegado.php");
    exit;
}

/** @var ObtenerTodasEncuestasUseCase $useCase */
$useCase   = $container->get(ObtenerTodasEncuestasUseCase::class);
$encuestas = $useCase->execute();

$uploadsDir = __DIR__ . '/../../uploads/sodexo/';

$zipFilename = "recibos_sodexo_" . date('Y-m-d_H-i') . ".zip";
$zipPath = sys_get_temp_dir() . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("No se pudo crear el archivo ZIP temporal.");
}

$hasFiles = false;

foreach ($encuestas as $enc) {
    $empleadoNombre = trim($enc->userName . ' ' . $enc->userSurnames);
    $empleadoNombreSeguro = preg_replace('/[^a-zA-Z0-9_-]/', '_', $empleadoNombre);
    $carpetaAgremiado = $enc->tipoEmpleado . '/' . $empleadoNombreSeguro . '_' . $enc->userId;

    $recibos = [];
    if ($enc->esAdministrativo()) {
        $recibos['Dic_2025'] = $enc->admDicRecibo;
        $recibos['Ene_2026'] = $enc->admEneRecibo;
        $recibos['Feb_2026'] = $enc->admFebRecibo;
        $recibos['Mar_2026'] = $enc->admMarRecibo;
    } else {
        $recibos['Dic_2025'] = $enc->docDicRecibo;
        $recibos['Mar_2026'] = $enc->docMarRecibo;
    }

    foreach ($recibos as $mes => $filename) {
        if ($filename !== null && file_exists($uploadsDir . $filename)) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $newName = $carpetaAgremiado . '/Recibo_' . $mes . '.' . $ext;
            $zip->addFile($uploadsDir . $filename, $newName);
            $hasFiles = true;
        }
    }
}

if (!$hasFiles) {
    $zip->addFromString('info.txt', 'No se encontraron recibos adjuntos en el sistema.');
}

$zip->close();

if (file_exists($zipPath)) {
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=' . $zipFilename);
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    unlink($zipPath);
    exit;
} else {
    die("Error inesperado al generar el archivo ZIP.");
}
