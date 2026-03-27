<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\Requests\Application\UseCase\ChangeRequestStatusUseCase;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /portal/solicitudes/panel.php');
    exit;
}

$userContext     = $container->get(UserContextInterface::class);
$changeUseCase   = $container->get(ChangeRequestStatusUseCase::class);
$authUser        = $userContext->getUser();

$privilegedRoles = ['administrador', 'finanzas', 'lider'];
$isPrivileged    = in_array($authUser->role->value, $privilegedRoles, true);

$requestId  = (int)($_POST['request_id'] ?? 0);
$newStatus  = trim($_POST['new_status'] ?? '');
$adminNotes = trim($_POST['admin_notes'] ?? '') ?: null;

if ($requestId === 0 || $newStatus === '') {
    header('Location: /portal/solicitudes/panel.php');
    exit;
}

try {
    $changeUseCase->execute(
        requestId:       $requestId,
        newStatusValue:  $newStatus,
        changedBy:       $authUser->userId,
        isPrivilegedRole: $isPrivileged,
        adminNotes:      $adminNotes,
    );
    $msg = urlencode('Estado actualizado correctamente.');
    header("Location: /portal/solicitudes/detalle.php?id={$requestId}&success={$msg}");
} catch (Throwable $e) {
    $msg = urlencode($e->getMessage());
    header("Location: /portal/solicitudes/detalle.php?id={$requestId}&error={$msg}");
}
exit;
