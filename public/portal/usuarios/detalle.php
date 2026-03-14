<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Utils\DocumentHelper;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($container->get(MiddlewareFactory::class)->auth());

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ./listado.php');
    exit;
}

$userRepository = $container->get(UserRepositoryInterface::class);
$user = $userRepository->findById($id);

if ($user === null) {
    header('Location: ./listado.php');
    exit;
}

// prepare documents info similar to portal/perfiles/ver.php
$docs = [
    'perfil' => DocumentHelper::normalizeUploadPath($user->personalInfo->photo ?? null),
    'afiliacion' => '',
    'comprobante_domicilio' => '',
    'ine' => '',
    'comprobante_pago' => '',
    'curp' => '',
];

$storedDocuments = $userRepository->findDocumentsByUserId($user->id);
foreach ($storedDocuments as $type => $path) {
    $docs[$type] = DocumentHelper::normalizeUploadPath($path);
}

$documentFields = DocumentHelper::resolveDocumentFieldsByRole($user->role);
$hasAnyDocument = false;
foreach ($documentFields as $field) {
    if (!empty($docs[$field['key']] ?? '')) {
        $hasAnyDocument = true;
        break;
    }
}

$container->get(RendererInterface::class)->render('./detalle.latte', [
    'user' => $user,
    'docs' => $docs,
    'documentFields' => $documentFields,
    'hasAnyDocument' => $hasAnyDocument,
]);


