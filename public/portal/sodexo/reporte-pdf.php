<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\Sodexo\Application\UseCase\ObtenerTodasEncuestasUseCase;
use App\Shared\Context\UserContext;
use App\Shared\Domain\Enum\RoleEnum;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

/** @var UserContext $userContext */
$userContext = $container->get(UserContext::class);
$authUser    = $userContext->get();

// Solo administradores y líderes pueden generar este reporte
if ($authUser === null) {
    header("Location: /cuentas/login.php");
    exit;
}

if (!in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider], true)) {
    header("Location: /portal/acceso-denegado.php");
    exit;
}

/** @var ObtenerTodasEncuestasUseCase $obtenerUseCase */
$obtenerUseCase = $container->get(ObtenerTodasEncuestasUseCase::class);
$encuestas      = $obtenerUseCase->execute();

/** @var RendererInterface $renderer */
$renderer = $container->get(RendererInterface::class);

/** @var Dompdf $pdf */
$pdf = $container->get(Dompdf::class);

// Cargar logo institucional como base64
$logoPath = __DIR__ . "/../../assets/images/logo.webp";
$logoSrc  = null;

if (is_file($logoPath)) {
    $logoData = file_get_contents($logoPath);
    if (is_string($logoData) && $logoData !== '') {
        $logoSrc = "data:image/webp;base64," . base64_encode($logoData);
    }
}

// Obtener color institucional
$primaryColor = "#611232";

try {
    $colorConfig = $container->get(GetColorUseCase::class)->execute();
    if ($colorConfig !== null && $colorConfig->primary !== '') {
        $primaryColor = $colorConfig->primary;
    }
} catch (\Throwable) {
    // Usar color por defecto
}

// Calcular totales globales para el resumen
$totalEncuestas      = count($encuestas);
$totalAdministrativos = 0;
$totalDocentes        = 0;
$sumaGlobalPagado     = 0.0;
$sumaGlobalAdeudo     = 0.0;

foreach ($encuestas as $e) {
    if ($e->esAdministrativo()) {
        $totalAdministrativos++;
    } else {
        $totalDocentes++;
    }
    $sumaGlobalPagado += $e->totalPagado();
    $sumaGlobalAdeudo += $e->totalAdeudo();
}

$html = $renderer->renderToString(
    __DIR__ . "/../../../templates/documents/sodexo-reporte.latte",
    [
        'encuestas'            => $encuestas,
        'generadoPor'          => $authUser->name,
        'generadoEn'           => (new \DateTimeImmutable())->format('d/m/Y H:i'),
        'logoSrc'              => $logoSrc,
        'primaryColor'         => $primaryColor,
        'totalEncuestas'       => $totalEncuestas,
        'totalAdministrativos' => $totalAdministrativos,
        'totalDocentes'        => $totalDocentes,
        'sumaGlobalPagado'     => $sumaGlobalPagado,
        'sumaGlobalAdeudo'     => $sumaGlobalAdeudo,
    ],
);

$pdf->loadHtml($html);
$pdf->render();

$filename = "reporte-sodexo-vales-despensa-" . date("YmdHis") . ".pdf";
$pdf->stream($filename, ["Attachment" => true]);

exit;
