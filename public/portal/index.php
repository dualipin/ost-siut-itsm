<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Dashboard\Application\UseCase\GetLiderDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetAdministradorDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetFinanzasDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetAgremiadoDashboardDataUseCase;
use App\Modules\Dashboard\Application\UseCase\GetPublicDashboardDataUseCase;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

$renderer = $container->get(RendererInterface::class);

// Route to appropriate dashboard based on user role
match ($user->role) {
    RoleEnum::Lider => renderLiderDashboard($container, $renderer, $userProvider),
    RoleEnum::Admin => renderAdministradorDashboard($container, $renderer, $userProvider),
    RoleEnum::Finanzas => renderFinanzasDashboard($container, $renderer, $userProvider),
    RoleEnum::Agremiado => renderAgremiadoDashboard($container, $renderer, $userProvider),
    RoleEnum::NoAgremiado => renderNoAgremiadoDashboard($container, $renderer),
};

function renderLiderDashboard($container, $renderer, $userProvider): void
{
    $useCase = $container->get(GetLiderDashboardDataUseCase::class);
    $data = $useCase->execute($userProvider);
    $renderer->render(__DIR__ . "/../../templates/dashboards/dashboard-lider.latte", $data);
}

function renderAdministradorDashboard($container, $renderer, $userProvider): void
{
    $useCase = $container->get(GetAdministradorDashboardDataUseCase::class);
    $data = $useCase->execute($userProvider);
    $renderer->render(__DIR__ . "/../../templates/dashboards/dashboard-administrador.latte", $data);
}

function renderFinanzasDashboard($container, $renderer, $userProvider): void
{
    $useCase = $container->get(GetFinanzasDashboardDataUseCase::class);
    $data = $useCase->execute($userProvider);
    $renderer->render(__DIR__ . "/../../templates/dashboards/dashboard-finanzas.latte", $data);
}

function renderAgremiadoDashboard($container, $renderer, $userProvider): void
{
    $useCase = $container->get(GetAgremiadoDashboardDataUseCase::class);
    $data = $useCase->execute($userProvider);
    $renderer->render(__DIR__ . "/../../templates/dashboards/dashboard-agremiado.latte", $data);
}

function renderNoAgremiadoDashboard($container, $renderer): void
{
    $useCase = $container->get(GetPublicDashboardDataUseCase::class);
    $data = $useCase->execute();
    $renderer->render(__DIR__ . "/../../templates/dashboards/dashboard-no-agremiado.latte", $data);
}

