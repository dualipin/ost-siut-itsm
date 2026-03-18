<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\ListTransparenciesUseCase;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$listUseCase = $container->get(ListTransparenciesUseCase::class);
$repository = $container->get(TransparencyRepositoryInterface::class);

session_start([
    'read_and_close' => true,
]);

$userContext = $container->get(UserContextInterface::class);
$user = $userContext->get();
$userId = $user ? $user->id : 0;

if ($userId > 0) {
    $documents = $listUseCase->executeForUser($userId);
} else {
    $documents = $listUseCase->executePublic();
}

$renderer->render("./transparencia.latte", [
    'documents' => $documents,
    'isAuthenticated' => $userId > 0
]);