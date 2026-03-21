<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Session\SessionInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\GetThreadDetailUseCase;
use App\Modules\Messaging\Application\UseCase\ReplyToQuestionUseCase;
use App\Modules\Messaging\Domain\Exception\ReplyValidationException;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();

$session = $container->get(SessionInterface::class);
$renderer = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$getDetailUseCase = $container->get(GetThreadDetailUseCase::class);
$replyUseCase = $container->get(ReplyToQuestionUseCase::class);

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
    $canManageVisibilityAndPdf = in_array($authenticatedUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);

    $action = $_POST['action'] ?? 'reply';

    try {
        if ($action === 'toggle_visibility') {
            if (!$canManageVisibilityAndPdf) {
                http_response_code(403);
                $session->set('toast', [
                    'type' => 'danger',
                    'message' => 'No tienes permisos para cambiar la visibilidad.',
                ]);
                header("Location: detalle.php?id={$threadId}");
                exit;
            }

            $visibility = $_POST['visibility'] ?? 'private';
            $toggleUseCase = $container->get(\App\Modules\Messaging\Application\UseCase\ToggleThreadVisibilityUseCase::class);
            $toggleUseCase->execute($threadId, $visibility);
            
            $session->set('toast', [
                'type' => 'success',
                'message' => 'Visibilidad actualizada correctamente.',
            ]);
        } else {
            $attachments = [];
            if (!empty($_FILES['adjuntos']['name'][0])) {
                foreach ($_FILES['adjuntos']['name'] as $i => $name) {
                    $attachments[] = [
                        'name'     => $name,
                        'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
                        'size'     => $_FILES['adjuntos']['size'][$i],
                        'type'     => $_FILES['adjuntos']['type'][$i],
                        'error'    => $_FILES['adjuntos']['error'][$i],
                    ];
                }
            }

            $replyUseCase->execute($threadId, $adminUserId, $replyBody, $attachments);
            $session->set('toast', [
                'type' => 'success',
                'message' => 'Respuesta enviada correctamente. El correo fue enviado al remitente.',
            ]);
        }
    } catch (ReplyValidationException $e) {
        $session->set('toast', [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ]);
    }

    header("Location: detalle.php?id={$threadId}");
    exit;
}

$authenticatedUser = $userContext->get();
$canManageVisibilityAndPdf = $authenticatedUser !== null
    && in_array($authenticatedUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);

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
    'canManageVisibilityAndPdf' => $canManageVisibilityAndPdf,
]);
