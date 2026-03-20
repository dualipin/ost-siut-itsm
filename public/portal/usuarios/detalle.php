<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use App\Shared\Utils\DocumentHelper;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Context\UserProviderInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($container->get(MiddlewareFactory::class)->auth());

$authUser = $container->get(UserProviderInterface::class)->get();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'validate_document' && $authUser !== null) {
        $documentTypeValue = trim((string) ($_POST['document_type'] ?? ''));
        $documentType = DocumentTypeEnum::tryFrom($documentTypeValue);

        if ($documentType === null) {
            header('Location: ./detalle.php?id=' . $id . '&doc_error=1');
            exit;
        }

        $validated = $userRepository->validateLatestDocumentByType(
            userId: $id,
            documentType: $documentType,
            validatedBy: $authUser->id,
        );

        if (!$validated) {
            header('Location: ./detalle.php?id=' . $id . '&doc_error=1');
            exit;
        }

        header('Location: ./detalle.php?id=' . $id . '&doc_validated=1');
        exit;
    }
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

$docStatuses = $userRepository->findDocumentStatusesByUserId($user->id);

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
    'docStatuses' => $docStatuses,
    'documentFields' => $documentFields,
    'hasAnyDocument' => $hasAnyDocument,
    'documentValidated' => (($_GET['doc_validated'] ?? '') === '1'),
    'documentValidationError' => (($_GET['doc_error'] ?? '') === '1'),
]);


