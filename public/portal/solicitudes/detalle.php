<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Requests\Application\UseCase\GetRequestDetailUseCase;
use App\Modules\Requests\Infrastructure\Persistence\PdoRequestRepository;
use App\Shared\Context\UserContextInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../bootstrap.php';


$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$useCase     = $container->get(GetRequestDetailUseCase::class);
$repository  = $container->get(PdoRequestRepository::class);
$authUser    = $userContext->get();


$logger = $container->get(LoggerInterface::class);

$requestId = (int)($_GET['id'] ?? 0);

if ($requestId === 0) {
    header('Location: /portal/solicitudes/mis-solicitudes.php');
    exit;
}

try {
    $data = $useCase->execute($requestId);
} catch (Throwable $e) {
    $logger->error('Error fetching request detail', ['exception' => $e]);
    header('Location: /portal/solicitudes/mis-solicitudes.php');
    exit;
}

$request         = $data['request'];
$privilegedRoles = ['administrador', 'finanzas', 'lider'];
$isPrivileged    = in_array($authUser->role->value, $privilegedRoles, true);

// Access control: owner OR privileged role
if (!$isPrivileged && !$request->isOwnedBy($authUser->id)) {
    header('Location: /portal/acceso-denegado.php');
    exit;
}

$history = $repository->findStatusHistory($requestId);

$data['history']     = $history;
$data['authUser']    = $authUser;
$data['isPrivileged'] = $isPrivileged;

$renderer->render(__DIR__ . '/../../../templates/solicitudes/detalle.latte', $data);
