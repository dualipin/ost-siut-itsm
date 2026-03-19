<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Context\UserProviderInterface;
use App\Modules\Transparency\Domain\Enum\TransparencyType;
use App\Modules\Transparency\Application\UseCase\ListTransparenciesUseCase;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

$exito = $error = null;
if (isset($_GET['error'])) {
    $error = trim((string)$_GET['error']);
}
if (isset($_GET['mensaje'])) {
    $exito = trim((string)$_GET['mensaje']);
}

$listUseCase = $container->get(ListTransparenciesUseCase::class);

// Fetch based on permissions
if ($user && ($user->role->value === 'administrador' || $user->role->value === 'lider')) {
    $todosDocumentos = $listUseCase->executeAllByType(TransparencyType::TRAMITES);
} else {
    $todosDocumentos = $listUseCase->executePublicByType(TransparencyType::TRAMITES);
}

$totalDocumentos = count($todosDocumentos);

$porPagina = 6;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$inicio = ($paginaActual - 1) * $porPagina;
$totalPaginas = (int)ceil($totalDocumentos / $porPagina);

$resultados = array_slice($todosDocumentos, $inicio, $porPagina);

$agrupado = [];
foreach ($resultados as $d) {
    $anio = $d->datePublished->format('Y');
    $mes = $d->datePublished->format('m');
    $agrupado[$anio][$mes][] = $d;
}

$data = [
    'agrupado' => $agrupado,
    'documentos' => $resultados,
    'totalPaginas' => $totalPaginas,
    'paginaActual' => $paginaActual,
    'error' => $error,
    'mensajeExito' => $exito,
    'miembro' => $user,
];

// As old code relies on ServicioLatte to inject variables, the modern renderer does it cleanly.
$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . '/index.latte', $data);