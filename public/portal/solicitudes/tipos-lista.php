<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Session\SessionInterface;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Requests\Application\UseCase\GetRequestTypesUseCase;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer    = $container->get(RendererInterface::class);
$userContext = $container->get(UserContextInterface::class);
$session     = $container->get(SessionInterface::class);
$authUser    = $userContext->get();

// Only privileged roles
$privilegedRoles = ['administrador', 'finanzas', 'lider'];
if (!in_array($authUser->role->value, $privilegedRoles, true)) {
    header('Location: /portal/acceso-denegado.php');
    exit;
}

$typesUseCase = $container->get(GetRequestTypesUseCase::class);
$data         = $typesUseCase->execute(false); // all types, including inactive

$data['authUser'] = $authUser;
$data['toast']    = $session->get('toast');
$session->remove('toast');

$renderer->render(__DIR__ . '/../../../templates/solicitudes/tipos-lista.latte', $data);
