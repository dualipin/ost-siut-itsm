<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\ManagePermissionsUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;

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
    
    // Solo aplica para documentos privados
    if (!$transparency->isPrivate) {
        header("Location: ./listado.php?error=public");
        exit;
    }
    
    // Obtenemos los usuarios y los permisos actuales
    $userRepository = $container->get(UserRepositoryInterface::class);
    $allUsers = $userRepository->listado(); // Asumiendo que listado() devuelve todos los UserSummary
    
    $transparencyRepository = $container->get(\App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface::class);
    $permissions = $transparencyRepository->findPermissionsByTransparencyId($id);
    $authorizedUserIds = array_map(fn($p) => $p->userId, $permissions);

} catch (TransparencyNotFoundException $e) {
    header("Location: ./listado.php?error=notfound");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manageUseCase = $container->get(ManagePermissionsUseCase::class);
    $selectedUserIds = $_POST['users'] ?? [];

    try {
        $manageUseCase->execute($id, $selectedUserIds);
        header("Location: ./permisos.php?id={$id}&success=updated");
        exit;
    } catch (Exception $e) {
        $error = "Error al actualizar los permisos.";
    }
}

$renderer->render("./permisos.latte", [
    "transparency" => $transparency,
    "allUsers" => $allUsers,
    "authorizedUserIds" => $authorizedUserIds,
    "error" => $error ?? null
]);
