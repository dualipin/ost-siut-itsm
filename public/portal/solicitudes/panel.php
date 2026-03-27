<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Requests\Application\UseCase\GetAllRequestsUseCase;
use App\Modules\Requests\Application\UseCase\GetRequestTypesUseCase;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$useCase     = $container->get(GetAllRequestsUseCase::class);
$typesUseCase = $container->get(GetRequestTypesUseCase::class);
$authUser    = $userContext->getUser();

// Only privileged roles
$privilegedRoles = ['administrador', 'finanzas', 'lider'];
if (!in_array($authUser->role->value, $privilegedRoles, true)) {
    header('Location: /portal/acceso-denegado.php');
    exit;
}

$folio      = $_GET['folio']    ?? null;
$typeId     = !empty($_GET['type_id']) ? (int)$_GET['type_id'] : null;
$status     = $_GET['status']   ?? null;
$dateFrom   = $_GET['date_from'] ?? null;
$dateTo     = $_GET['date_to']   ?? null;
$sortBy     = $_GET['sort_by']   ?? 'created_at';
$sortOrder  = $_GET['sort_order'] ?? 'DESC';

$data = $useCase->execute($folio, $typeId, $status, $dateFrom, $dateTo, $sortBy, $sortOrder);
$data['types']    = $typesUseCase->execute(false)['types'];
$data['authUser'] = $authUser;

$renderer->render(__DIR__ . '/../../../templates/solicitudes/panel.latte', $data);
