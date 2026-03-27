<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\Requests\Application\UseCase\GenerateRequestPdfUseCase;
use App\Modules\Requests\Application\UseCase\GetRequestDetailUseCase;
use App\Shared\Context\UserContextInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$userContext = $container->get(UserContextInterface::class);
$detailUseCase = $container->get(GetRequestDetailUseCase::class);
$pdfUseCase  = $container->get(GenerateRequestPdfUseCase::class);
$authUser    = $userContext->get();
$logger      = $container->get(LoggerInterface::class);

$requestId = (int)($_GET['id'] ?? 0);

if ($requestId === 0) {
    header('Location: /portal/solicitudes/mis-solicitudes.php');
    exit;
}

try {
    $data = $detailUseCase->execute($requestId);
} catch (Throwable $e) {
    $logger->error('Error fetching request detail for PDF', ['exception' => $e]);
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

try {
    $pdfUseCase->execute($requestId);
} catch (Throwable $e) {
    $logger->error('Error generating PDF for request', ['request_id' => $requestId, 'exception' => $e]);
    header('Location: /portal/solicitudes/detalle.php?id=' . $requestId . '&error=' . urlencode('No se pudo generar el documento PDF.'));
    exit;
}
