<?php

use App\Bootstrap;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$repository = $container->get(TransparencyRepositoryInterface::class);
$getUseCase = $container->get(GetTransparencyUseCase::class);

session_start([
    'read_and_close' => true,
]);

$transparencyId = (int) ($_GET['tid'] ?? 0);
$attachmentId = (int) ($_GET['aid'] ?? 0);

if ($transparencyId === 0 || $attachmentId === 0) {
    http_response_code(400);
    die('Parámetros inválidos.');
}

try {
    $transparency = $getUseCase->execute($transparencyId);
    
    // Verificación de privacidad
    if ($transparency->isPrivate) {
        $userContext = $container->get(UserContextInterface::class);
        $user = $userContext->get();
        $userId = $user ? $user->id : 0;
        
        if ($userId === 0) {
             http_response_code(403);
             die('Acceso denegado. Se requiere iniciar sesión.');
        }
        
        $permissions = $repository->findPermissionsByTransparencyId($transparencyId);
        $hasPermission = false;
        foreach ($permissions as $p) {
            if ($p->userId === $userId) {
                $hasPermission = true;
                break;
            }
        }
        $userRole = $user ? $user->role->value : null;
        if (!$hasPermission && $userRole !== 'ADMIN') {
            http_response_code(403);
            die('Acceso denegado. No posee permisos para este documento.');
        }
    }
    
    $attachments = $repository->findAttachmentsByTransparencyId($transparencyId);
    $targetAttachment = null;
    foreach ($attachments as $att) {
        if ($att->id === $attachmentId) {
            $targetAttachment = $att;
            break;
        }
    }
    
    if ($targetAttachment === null || $targetAttachment->attachmentType->value === 'ENLACE') {
        http_response_code(404);
        die('Archivo no encontrado o es un enlace.');
    }
    
    // Resolución de la ruta física según la regla de privacidad
    $basePath = $transparency->isPrivate ? dirname(__DIR__) . '/uploads/transparency' : __DIR__ . '/uploads/transparency';
    $filePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($targetAttachment->filePath, DIRECTORY_SEPARATOR);
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        die('El archivo físico no existe en el servidor.');
    }
    
    $mimeType = $targetAttachment->mimeType ?: 'application/octet-stream';
    
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($targetAttachment->filePath) . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    
    readfile($filePath);
    exit;
    
} catch (TransparencyNotFoundException $e) {
    http_response_code(404);
    die('Documento principal no encontrado.');
}
