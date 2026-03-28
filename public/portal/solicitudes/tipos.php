<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Session\SessionInterface;
use App\Modules\Requests\Application\UseCase\CreateRequestTypeUseCase;
use App\Modules\Requests\Application\UseCase\UpdateRequestTypeUseCase;
use App\Modules\Requests\Domain\Exception\RequestTypeNotFoundException;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$session     = $container->get(SessionInterface::class);
$userContext = $container->get(UserContextInterface::class);
$authUser    = $userContext->get();

// Only privileged roles
$privilegedRoles = ['administrador', 'finanzas', 'lider'];
if (!in_array($authUser->role->value, $privilegedRoles, true)) {
    $session->set('toast', ['type' => 'danger', 'message' => 'Acceso denegado.']);
    header('Location: /portal/solicitudes/panel.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /portal/solicitudes/tipos-lista.php');
    exit;
}

$action = $_POST['action'] ?? 'create';
$back   = '/portal/solicitudes/tipos-lista.php';

try {
    match ($action) {
        'create' => (function () use ($container, $session) {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '') ?: null;
            $active      = isset($_POST['active']) && $_POST['active'] === '1';

            if ($name === '') {
                throw new InvalidArgumentException('El nombre del tipo de solicitud es obligatorio.');
            }

            $container->get(CreateRequestTypeUseCase::class)->execute($name, $description, $active);
            $session->set('toast', ['type' => 'success', 'message' => "Tipo «{$name}» registrado correctamente."]);
        })(),

        'update' => (function () use ($container, $session) {
            $id          = (int)($_POST['id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '') ?: null;
            $active      = isset($_POST['active']) && $_POST['active'] === '1';

            if ($id <= 0) {
                throw new InvalidArgumentException('ID de tipo inválido.');
            }

            $container->get(UpdateRequestTypeUseCase::class)->execute($id, $name, $description, $active);
            $session->set('toast', ['type' => 'success', 'message' => "Tipo «{$name}» actualizado correctamente."]);
        })(),

        'toggle' => (function () use ($container, $session) {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('ID de tipo inválido.');
            }

            $container->get(UpdateRequestTypeUseCase::class)->toggle($id);
            $session->set('toast', ['type' => 'success', 'message' => 'Estado actualizado correctamente.']);
        })(),

        default => throw new InvalidArgumentException("Acción desconocida: {$action}"),
    };
} catch (RequestTypeNotFoundException $e) {
    $session->set('toast', ['type' => 'danger', 'message' => $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    $session->set('toast', ['type' => 'danger', 'message' => $e->getMessage()]);
} catch (Throwable) {
    $session->set('toast', ['type' => 'danger', 'message' => 'Ocurrió un error inesperado.']);
}

header("Location: {$back}");
exit;
