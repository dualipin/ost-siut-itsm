<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserProviderInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\UpdateTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\AddAttachmentUseCase;
use App\Modules\Transparency\Domain\Enum\AttachmentType;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use App\Modules\Transparency\Domain\Repository\FileStorageInterface;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

if (!$user || ($user->role->value !== 'administrador' && $user->role->value !== 'lider')) {
    header('Location: index.php?error=' . urlencode('Permisos insuficientes'));
    exit;
}

$getUseCase = $container->get(GetTransparencyUseCase::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id_doc'];
    $titulo = trim((string)($_POST['title'] ?? ''));
    $contenido = trim((string)($_POST['summary'] ?? ''));
    $fecha_documento = trim((string)($_POST['date_published'] ?? ''));
    $privado = isset($_POST['is_private']);

    if ($titulo === '') {
        header('Location: actualizar.php?id_doc=' . $id . '&error=' . urlencode('El título es obligatorio'));
        exit;
    }

    if ($fecha_documento !== '') {
        $fechaObj = date_create_from_format('Y-m-d', $fecha_documento);
        if (!$fechaObj) {
            header('Location: actualizar.php?id_doc=' . $id . '&error=' . urlencode('Fecha inválida'));
            exit;
        }
    } else {
        $fecha_documento = date('Y-m-d');
    }

    try {
        $transparency = $getUseCase->execute($id);
        $repo = $container->get(TransparencyRepositoryInterface::class);
        $fileStorage = $container->get(FileStorageInterface::class);
        
        $updateUseCase = $container->get(UpdateTransparencyUseCase::class);
        $updateUseCase->execute(
            id: $transparency->id,
            title: $titulo,
            summary: $contenido ?: null,
            typeValue: $transparency->type->value,
            datePublished: $fecha_documento,
            isPrivate: $privado
        );

        $adjuntosActuales = $repo->findAttachmentsByTransparencyId($transparency->id);

        $removeAttachmentIds = [];
        if (isset($_POST['remove_attachment_ids']) && is_array($_POST['remove_attachment_ids'])) {
            foreach ($_POST['remove_attachment_ids'] as $rawAttachmentId) {
                $attachmentId = (int)$rawAttachmentId;
                if ($attachmentId > 0) {
                    $removeAttachmentIds[$attachmentId] = true;
                }
            }
        }

        if ($removeAttachmentIds !== []) {
            foreach ($adjuntosActuales as $adjuntoActual) {
                if (!isset($removeAttachmentIds[$adjuntoActual->id])) {
                    continue;
                }

                if ($adjuntoActual->attachmentType->value !== AttachmentType::ENLACE->value) {
                    $fileStorage->delete($adjuntoActual->filePath, $transparency->isPrivate);
                }

                $repo->deleteAttachment($adjuntoActual->id);
            }
        }

        $addAttachmentUseCase = $container->get(AddAttachmentUseCase::class);

        if (isset($_POST['attachments']) && is_array($_POST['attachments'])) {
            foreach ($_POST['attachments'] as $index => $attPost) {
                $type = isset($attPost['type']) ? (string)$attPost['type'] : '';
                $descriptionRaw = isset($attPost['description']) ? trim((string)$attPost['description']) : '';
                $description = $descriptionRaw !== '' ? $descriptionRaw : null;

                if ($type === AttachmentType::ENLACE->value) {
                    $url = trim((string)($attPost['url'] ?? ''));
                    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $addAttachmentUseCase->execute(
                        transparencyId: $transparency->id,
                        sourcePath: $url,
                        originalFilename: $url,
                        mimeType: 'text/uri-list',
                        attachmentTypeValue: AttachmentType::ENLACE->value,
                        description: $description
                    );
                    continue;
                }

                if (!isset($_FILES['attachments_files']['error'][$index]) || $_FILES['attachments_files']['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmpName = (string)$_FILES['attachments_files']['tmp_name'][$index];
                $mime = mime_content_type($tmpName);
                if ($mime === false) {
                    $mime = 'application/octet-stream';
                }

                $addAttachmentUseCase->execute(
                    transparencyId: $transparency->id,
                    sourcePath: $tmpName,
                    originalFilename: basename((string)$_FILES['attachments_files']['name'][$index]),
                    mimeType: $mime,
                    attachmentTypeValue: $type !== '' ? $type : AttachmentType::OTRO->value,
                    description: $description
                );
            }
        }

        // Compatibilidad con el campo anterior de archivo único.
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($_FILES['archivo']['tmp_name']);
            if ($mime === false) {
                $mime = 'application/octet-stream';
            }

            $addAttachmentUseCase->execute(
                transparencyId: $transparency->id,
                sourcePath: $_FILES['archivo']['tmp_name'],
                originalFilename: basename($_FILES['archivo']['name']),
                mimeType: $mime,
                attachmentTypeValue: AttachmentType::OTRO->value,
                description: null
            );
        }

        header('Location: detalle.php?id_doc=' . $id . '&mensaje=' . urlencode('Documento actualizado con éxito.'));
        exit;
    } catch (Exception $e) {
        header('Location: actualizar.php?id_doc=' . $id . '&error=' . urlencode('Error al actualizar: ' . $e->getMessage()));
        exit;
    }
}

if (!isset($_GET['id_doc']) || !is_numeric($_GET['id_doc'])) {
    http_response_code(400);
    header('Location: index.php?error=' . urlencode('ID de documento inválido.'));
    exit;
}

$id = (int)$_GET['id_doc'];

try {
    $documento = $getUseCase->execute($id);
} catch (Exception $e) {
    http_response_code(404);
    header('Location: index.php?error=' . urlencode('Documento no encontrado.'));
    exit;
}

$repo = $container->get(TransparencyRepositoryInterface::class);
$adjuntos = $repo->findAttachmentsByTransparencyId($documento->id);

$datos = [
    'documento' => $documento,
    'adjuntos'  => $adjuntos,
    'error' => $_GET['error'] ?? null,
    'mensaje' => $_GET['mensaje'] ?? null,
];

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . '/actualizar.latte', $datos);

