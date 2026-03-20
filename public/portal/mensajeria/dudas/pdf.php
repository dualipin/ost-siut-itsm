<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Modules\Messaging\Application\UseCase\GenerateThreadPdfUseCase;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$session = $container->get(\App\Infrastructure\Session\SessionInterface::class);
$authUser = $container->get(\App\Shared\Context\UserProviderInterface::class)->get();

// Solo admin o lider pueden exportar a PDF
if ($authUser === null || !in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider])) {
    http_response_code(403);
    die('Acceso denegado');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('ID de registro inválido');
}

try {
    $useCase = $container->get(GenerateThreadPdfUseCase::class);
    $useCase->execute($id);
} catch (Throwable $e) {
    die('Error al generar el PDF: ' . $e->getMessage());
}
