<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserProviderInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Domain\Repository\TransparencyRepositoryInterface;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

if (!isset($_GET['id_doc']) || !is_numeric($_GET['id_doc'])) {
    http_response_code(400);
    header('Location: index.php');
    exit('ID de documento inválido.');
}

$getUseCase = $container->get(GetTransparencyUseCase::class);

$documento = $getUseCase->execute((int)$_GET['id_doc']);

if (!$documento) {
    http_response_code(404);
    $error = 'Documento no encontrado.';
    header('Location: index.php?error=' . urlencode($error));
    exit;
}

// In real app, the repo is in container. We might need the repo interface to fetch attachments.
// Or we could have a `GetAttachmentsUseCase`, but using repo interface is fine here since it's just finding.
$repo = $container->get(TransparencyRepositoryInterface::class);
$adjuntos = $repo->findAttachmentsByTransparencyId($documento->id);

$data = [
    'documento' => $documento,
    'adjuntos'  => $adjuntos,
    'miembro'   => $user,
];

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . '/detalle.latte', $data);