<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Sodexo\Application\UseCase\ObtenerTodasEncuestasUseCase;
use App\Shared\Context\UserContext;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

/** @var UserContext $userContext */
$userContext = $container->get(UserContext::class);
$authUser    = $userContext->get();

// Solo administradores y líderes pueden ver el reporte
if ($authUser === null) {
    header("Location: /cuentas/login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

if (!in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider], true)) {
    header("Location: /portal/acceso-denegado.php");
    exit;
}

$renderer = $container->get(RendererInterface::class);

/** @var ObtenerTodasEncuestasUseCase $useCase */
$useCase   = $container->get(ObtenerTodasEncuestasUseCase::class);
$encuestas = $useCase->execute();

// Calcular resumen global
$totalAgremiados      = count($encuestas);
$totalAdministrativos = 0;
$totalDocentes        = 0;
$granTotalPagado      = 0.0;
$granTotalAdeudo      = 0.0;

foreach ($encuestas as $enc) {
    if ($enc->tipoEmpleado === 'administrativo') {
        $totalAdministrativos++;
    } else {
        $totalDocentes++;
    }
    $granTotalPagado += $enc->totalPagado();
    $granTotalAdeudo += $enc->totalAdeudo();
}

$renderer->render("./reporte.latte", [
    'encuestas'           => $encuestas,
    'totalAgremiados'     => $totalAgremiados,
    'totalAdministrativos'=> $totalAdministrativos,
    'totalDocentes'       => $totalDocentes,
    'granTotalPagado'     => $granTotalPagado,
    'granTotalAdeudo'     => $granTotalAdeudo,
]);
