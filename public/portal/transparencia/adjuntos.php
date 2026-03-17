<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\AddAttachmentUseCase;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$getUseCase = $container->get(GetTransparencyUseCase::class);

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id === 0) {
    header("Location: ./listado.php");
    exit;
}

try {
    $transparency = $getUseCase->execute($id);
    
    // Obtenemos manualmente los archivos desde el repositorio ya que la entidad principal los desacopla
    $repository = $container->get(\App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface::class);
    $attachments = $repository->findAttachmentsByTransparencyId($id);

} catch (TransparencyNotFoundException $e) {
    header("Location: ./listado.php?error=notfound");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add') {
        $addUseCase = $container->get(AddAttachmentUseCase::class);
        $type = $_POST['attachment_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        try {
            if ($type === 'ENLACE') {
                $url = $_POST['url'] ?? '';
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Debe ingresar una URL válida.');
                }
                $addUseCase->execute($id, $url, $url, 'text/uri-list', $type, $description);
            } else {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Debe seleccionar un archivo válido para subir.');
                }

                $file = $_FILES['file'];
                $addUseCase->execute(
                    $id, 
                    $file['tmp_name'], 
                    $file['name'], 
                    $file['type'] ?: 'application/octet-stream', 
                    $type, 
                    $description
                );
            }
            
            header("Location: ./adjuntos.php?id={$id}&success=added");
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } 
    elseif ($_POST['action'] === 'delete') {
        $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
        if ($attachmentId > 0) {
            try {
                // Instanciamos el local storage y repositorio solo para esto (se recomienda UseCase a futuro pero mantengamoslo simple en CLI PHP)
                $fileStorage = $container->get(\App\Modules\Transparency\Domain\Repository\FileStorageInterface::class);
                
                foreach ($attachments as $att) {
                    if ($att->id === $attachmentId) {
                        if ($att->attachmentType->value !== 'ENLACE') {
                            $fileStorage->delete($att->filePath);
                        }
                        $repository->deleteAttachment($attachmentId);
                        break;
                    }
                }
                
                header("Location: ./adjuntos.php?id={$id}&success=deleted");
                exit;
            } catch (Exception $e) {
                $error = "Error al eliminar el archivo adjunto.";
            }
        }
    }
}

$renderer->render("./adjuntos.latte", [
    "transparency" => $transparency,
    "attachments" => $attachments,
    "error" => $error ?? null
]);
