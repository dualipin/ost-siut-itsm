<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\ListTransparenciesUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$getUseCase = $container->get(GetTransparencyUseCase::class);
$listUseCase = $container->get(ListTransparenciesUseCase::class);
$repository = $container->get(TransparencyRepositoryInterface::class);
$userContext = $container->get(UserContextInterface::class);

$authenticatedUser = $userContext->get();
if ($authenticatedUser === null) {
    header('Location: /cuentas/login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: ./listado.php');
    exit;
}

$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$isPrivileged = in_array($authenticatedUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);

try {
    $transparency = $getUseCase->execute($id);

    if (!$isPrivileged && $transparency->isPrivate) {
        $allowedTransparencies = $listUseCase->executePublicOrPermittedForUser($authenticatedUser->id);
        $hasPermission = false;

        foreach ($allowedTransparencies as $allowedTransparency) {
            if ($allowedTransparency->id === $id) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            header('Location: ./listado.php?error=unauthorized');
            exit;
        }
    }

    $attachments = $repository->findAttachmentsByTransparencyId($id);
    if (!empty($attachments)) {
        $direction = $order === 'asc' ? 1 : -1;
        usort($attachments, static function ($left, $right) use ($direction): int {
            $leftValue = $left->dateUpload?->getTimestamp();
            $rightValue = $right->dateUpload?->getTimestamp();

            if ($leftValue === null && $rightValue === null) {
                return $left->id <=> $right->id;
            }

            if ($leftValue === null) {
                return 1;
            }

            if ($rightValue === null) {
                return -1;
            }

            if ($leftValue === $rightValue) {
                return $left->id <=> $right->id;
            }

            return ($leftValue <=> $rightValue) * $direction;
        });
    }
} catch (TransparencyNotFoundException $e) {
    header('Location: ./listado.php?error=notfound');
    exit;
}

$renderer->render('./detalle.latte', [
    'transparency' => $transparency,
    'attachments' => $attachments,
    'isPrivileged' => $isPrivileged,
    'order' => $order,
]);
