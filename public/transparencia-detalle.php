<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();
$renderer = $container->get(RendererInterface::class);
$getUseCase = $container->get(GetTransparencyUseCase::class);
$repository = $container->get(TransparencyRepositoryInterface::class);

session_start([
    'read_and_close' => true,
]);

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userRole = $_SESSION['user_role'] ?? null; // Si hay rol de ADMIN, tal vez permitir bypass. Lo dejaremos validado por permiso.

$id = (int) ($_GET['id'] ?? 0);

if ($id === 0) {
    header("Location: ./transparencia.php");
    exit;
}

try {
    $transparency = $getUseCase->execute($id);
    
    // Verificación de Privacidad
    if ($transparency->isPrivate) {
        if ($userId === 0) {
            // Usuario anónimo rechazo a documento privado
            header("Location: ./transparencia.php");
            exit;
        }
        
        $permissions = $repository->findPermissionsByTransparencyId($id);
        $hasPermission = false;
        
        foreach ($permissions as $p) {
            if ($p->userId === $userId) {
                $hasPermission = true;
                break;
            }
        }
        
        // Permitir siempre si es 'ADMIN' asumiendo que el enum Role tiene ADMIN (opcional, por si el administrador intenta verla desde la vista pública)
        // Por consistencia estricta, si es admin debería poder. Pero si la interfaz no expone rol, confiaremos en los permisos directos + rol.
        if (!$hasPermission && $userRole !== 'ADMIN') {
            header("Location: ./transparencia.php");
            exit;
        }
    }
    
    $attachments = $repository->findAttachmentsByTransparencyId($id);

} catch (TransparencyNotFoundException $e) {
    header("Location: ./transparencia.php");
    exit;
}

$renderer->render("./transparencia-detalle.latte", [
    'transparency' => $transparency,
    'attachments' => $attachments
]);
