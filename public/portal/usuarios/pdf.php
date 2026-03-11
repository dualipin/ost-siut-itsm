<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($container->get(MiddlewareFactory::class)->auth());

$userRepository = $container->get(UserRepositoryInterface::class);
$users = $userRepository->listado(onlyActive: true);

$logoPath = __DIR__ . '/../../assets/images/logo.jpg';
$logoSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));

$color = $container->get(GetColorUseCase::class)->execute();
$primaryColor = $color->primary;

// Compute accessible text color (white or black) over primary background
$hex = ltrim($primaryColor, '#');
$r = hexdec(substr($hex, 0, 2)) / 255;
$g = hexdec(substr($hex, 2, 2)) / 255;
$b = hexdec(substr($hex, 4, 2)) / 255;
$r = $r <= 0.03928 ? $r / 12.92 : (($r + 0.055) / 1.055) ** 2.4;
$g = $g <= 0.03928 ? $g / 12.92 : (($g + 0.055) / 1.055) ** 2.4;
$b = $b <= 0.03928 ? $b / 12.92 : (($b + 0.055) / 1.055) ** 2.4;
$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
$primaryTextColor = (($luminance + 0.05) / (0.0 + 0.05)) > ((1.0 + 0.05) / ($luminance + 0.05))
    ? '#000000'
    : '#ffffff';

$html = $container->get(RendererInterface::class)->renderToString('./pdf.latte', [
    'users' => $users,
    'generatedAt' => (new DateTimeImmutable())->format('d/m/Y H:i'),
    'logoSrc' => $logoSrc,
    'primaryColor' => $primaryColor,
    'primaryTextColor' => $primaryTextColor,
]);

$pdf = $container->get(Dompdf::class);
$pdf->loadHtml($html);
$pdf->render();
$pdf->stream('usuarios-activos.pdf', ['Attachment' => true]);
