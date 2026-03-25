<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Session\SessionInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\GetThreadDetailUseCase;
use App\Modules\Messaging\Application\UseCase\ReplyToContactUseCase;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$session = $container->get(SessionInterface::class);
$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$getDetailUseCase = $container->get(GetThreadDetailUseCase::class);
$replyUseCase = $container->get(ReplyToContactUseCase::class);

$authenticatedUser = $userContext->get();

if ($authenticatedUser === null) {
    header('Location: /portal/acceso-denegado.php');
    exit;
}

$threadIdRaw = filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW);
$threadIdNormalized = is_string($threadIdRaw)
    ? trim($threadIdRaw, " \t\n\r\0\x0B\"'")
    : '';

if ($threadIdNormalized === '' || !ctype_digit($threadIdNormalized)) {
    header('Location: index.php');
    exit;
}

$threadId = (int) $threadIdNormalized;

if ($threadId <= 0) {
    header('Location: index.php');
    exit;
}

// Handle POST (reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $replyBody = trim((string) ($_POST['reply_body'] ?? ''));

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

$isStaffUser = in_array($authenticatedUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);
$isThreadOwner = $detail['thread']->senderId !== null && $detail['thread']->senderId === $authenticatedUser->id;

if (!$isStaffUser && !$isThreadOwner) {
    header('Location: /portal/acceso-denegado.php');
    exit;
}

$renderer->render(__DIR__ . '/detalle.latte', [
    'thread' => $detail['thread'],
    'messages' => $detail['messages'],
    'authUser' => $authenticatedUser,
]);
