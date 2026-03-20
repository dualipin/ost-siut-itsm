<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Session\SessionInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\GetThreadDetailUseCase;
use App\Modules\Messaging\Application\UseCase\ReplyToContactUseCase;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$session = $container->get(SessionInterface::class);
$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$getDetailUseCase = $container->get(GetThreadDetailUseCase::class);
$replyUseCase = $container->get(ReplyToContactUseCase::class);

$threadId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($threadId === null || $threadId === false) {
    header('Location: index.php');
    exit;
}

// Handle POST (reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $replyBody = trim((string) ($_POST['reply_body'] ?? ''));
    $authenticatedUser = $userContext->get();

    if ($authenticatedUser === null) {
        header('Location: /portal/acceso-denegado.php');
        exit;
    }

    $adminUserId = $authenticatedUser->id;

    try {
        $replyUseCase->execute($threadId, $adminUserId, $replyBody);
        $session->set('toast', [
            'type' => 'success',
            'message' => 'Respuesta enviada correctamente. El correo fue enviado al remitente.',
        ]);
    } catch (ReplyValidationException $e) {
        $session->set('toast', [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ]);
    }

    header("Location: detalle.php?id={$threadId}");
    exit;
}

// GET: show detail
try {
    $detail = $getDetailUseCase->execute($threadId);
} catch (ReplyValidationException) {
    header('Location: index.php');
    exit;
}

$renderer->render(__DIR__ . '/detalle.latte', [
    'thread' => $detail['thread'],
    'messages' => $detail['messages'],
]);
