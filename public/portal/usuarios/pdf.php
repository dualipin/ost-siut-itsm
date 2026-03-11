<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use Dompdf\Dompdf;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($container->get(MiddlewareFactory::class)->auth());

$userRepository = $container->get(UserRepositoryInterface::class);
$users = $userRepository->listado(onlyActive: true);

$html = $container->get(RendererInterface::class)->renderToString('./pdf.latte', [
    'users' => $users,
    'generatedAt' => (new DateTimeImmutable())->format('d/m/Y H:i'),
]);

$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('Letter');

$options = $pdf->getOptions();
$options->setIsRemoteEnabled(true);
$pdf->setOptions($options);

$pdf->render();
$pdf->stream('usuarios-activos.pdf', ['Attachment' => true]);
