<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\CashBoxes\Application\UseCase\BuildFiscalReportUseCase;
use App\Modules\CashBoxes\Application\UseCase\SaveFinancialReportUseCase;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$urlBuilder = $container->get(UrlBuilder::class);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $urlBuilder->to('/portal/cajas/informe.php'));
    exit;
}

$userContext = $container->get(UserContextInterface::class);
$user = $userContext->get();
if ($user === null) {
    header('Location: ' . $urlBuilder->to('/portal/auth/login.php'));
    exit;
}
if (!in_array($user->role, [RoleEnum::Admin, RoleEnum::Lider, RoleEnum::Finanzas], true)) {
    header('Location: ' . $urlBuilder->to('/portal/index.php'));
    exit;
}

$boxId = isset($_POST['box_id']) && $_POST['box_id'] !== '' ? (int) $_POST['box_id'] : null;
$periodStart = (string) ($_POST['period_start'] ?? '');
$periodEnd = (string) ($_POST['period_end'] ?? '');

$start = DateTimeImmutable::createFromFormat('Y-m-d', $periodStart);
$end = DateTimeImmutable::createFromFormat('Y-m-d', $periodEnd);
if ($start === false || $end === false || $start > $end) {
    header('Location: ' . $urlBuilder->to('/portal/cajas/informe.php', ['error' => 'Rango de fechas inválido.']));
    exit;
}

try {
    $buildUseCase = $container->get(BuildFiscalReportUseCase::class);
    $reportData = $buildUseCase->execute($periodStart, $periodEnd, $boxId);

    $logoPath = __DIR__ . '/../../../../public/assets/images/logo.jpg';
    $logoSrc = '';
    if (is_file($logoPath)) {
        $logoSrc = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($logoPath));
    }

    $color = $container->get(GetColorUseCase::class)->execute();
    $primaryColor = $color->primary;

    $renderer = $container->get(RendererInterface::class);
    $html = $renderer->renderToString(
        __DIR__ . '/../../../../templates/documents/informe-fiscal.latte',
        [
            'report' => $reportData,
            'generatedAt' => (new DateTimeImmutable())->format('d/m/Y H:i'),
            'logoSrc' => $logoSrc,
            'primaryColor' => $primaryColor,
        ]
    );

    $pdf = $container->get(Dompdf::class);
    $pdf->setPaper('Letter', 'portrait');
    $pdf->loadHtml($html);
    $pdf->render();

    $filename = 'informe-fiscal-' . $periodStart . '-a-' . $periodEnd . '-' . date('YmdHis') . '.pdf';
    $relativePath = '/uploads/finanzas/reportes/' . $filename;
    $absoluteDir = __DIR__ . '/../../../../public/uploads/finanzas/reportes';
    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0755, true);
    }

    $binary = $pdf->output();
    file_put_contents($absoluteDir . '/' . $filename, $binary);

    $saveUseCase = $container->get(SaveFinancialReportUseCase::class);
    $saveUseCase->execute(
        $boxId,
        $user->id,
        $periodStart,
        $periodEnd,
        $relativePath,
        $reportData['summary']
    );

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $binary;
    exit;
} catch (Throwable $exception) {
    header('Location: ' . $urlBuilder->to('/portal/cajas/informe.php', ['error' => $exception->getMessage()]));
    exit;
}
