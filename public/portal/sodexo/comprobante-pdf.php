<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\Sodexo\Application\UseCase\ObtenerEncuestaUseCase;
use App\Shared\Context\UserContext;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

/** @var UserContext $userContext */
$userContext = $container->get(UserContext::class);
$authUser    = $userContext->get();

if ($authUser === null) {
    header("Location: /cuentas/login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
    exit;
}

/** @var ObtenerEncuestaUseCase $obtenerUseCase */
$obtenerUseCase = $container->get(ObtenerEncuestaUseCase::class);
$encuesta       = $obtenerUseCase->execute($authUser->id);

if ($encuesta === null) {
    header("Location: /portal/sodexo/solicitud.php");
    exit;
}

/** @var UserRepositoryInterface $userRepository */
$userRepository = $container->get(UserRepositoryInterface::class);
$userFull       = $userRepository->findById($authUser->id);

/** @var RendererInterface $renderer */
$renderer = $container->get(RendererInterface::class);

/** @var Dompdf $pdf */
$pdf = $container->get(Dompdf::class);

// Logo institucional
$logoPath = __DIR__ . "/../../assets/images/logo.webp";
$logoSrc  = null;

if (is_file($logoPath)) {
    $logoData = file_get_contents($logoPath);
    if (is_string($logoData) && $logoData !== '') {
        $logoSrc = "data:image/webp;base64," . base64_encode($logoData);
    }
}

// Opciones de seguridad para Dompdf en local
$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$options->setChroot(__DIR__ . "/../../"); 
$pdf->setOptions($options);

// Configuración de color
$primaryColor = "#611232";
$secondaryColor = "#a57f2c";

try {
    $colorConfig = $container->get(GetColorUseCase::class)->execute();
    if ($colorConfig !== null) {
        if ($colorConfig->primary !== '') $primaryColor = $colorConfig->primary;
        if ($colorConfig->secondary !== '') $secondaryColor = $colorConfig->secondary;
    }
} catch (\Throwable) {}

$html = $renderer->renderToString(
    __DIR__ . "/../../../templates/documents/sodexo-comprobante.latte",
    [
        'user'           => $userFull,
        'encuesta'       => $encuesta,
        'logoSrc'        => $logoSrc,
        'primaryColor'   => $primaryColor,
        'secondaryColor' => $secondaryColor,
        'generadoEn'     => (new \DateTimeImmutable())->format('d/m/Y H:i'),
    ]
);

$pdf->loadHtml($html);
$pdf->render();

$firmaCurp = $encuesta->firmaCurp ?? 'SIN-CURP';
$filename = "comprobante-sodexo-" . $firmaCurp . "-" . date("Ymd") . ".pdf";
$pdf->stream($filename, ["Attachment" => true]);
exit;
