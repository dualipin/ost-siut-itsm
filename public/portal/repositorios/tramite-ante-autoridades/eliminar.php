<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Shared\Context\UserProviderInterface;
use App\Modules\Transparency\Application\UseCase\DeleteTransparencyUseCase;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: index.php');
    exit('Método no permitido');
}

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

if (!$user || ($user->role->value !== 'administrador' && $user->role->value !== 'lider')) {
    header('Location: index.php?error=' . urlencode('Permisos insuficientes'));
    exit;
}

if (!isset($_POST['id_doc'])) {
    header('Location: index.php?error=' . urlencode('ID de documento inválido.'));
    exit;
}

try {
    $deleteUseCase = $container->get(DeleteTransparencyUseCase::class);
    $deleteUseCase->execute((int)$_POST['id_doc']);
    header('Location: index.php?mensaje=' . urlencode('Eliminado con éxito'));
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Error interno: ' . $e->getMessage()));
    exit;
}