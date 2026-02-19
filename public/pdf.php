<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use Dompdf\Dompdf;

require_once __DIR__.'/../bootstrap.php';

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

$dompdf = $container->get(Dompdf::class);


$html = $renderer->renderToString(__DIR__.'/../templates/prestamo/pagare-document.latte');


$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream('pagare.pdf', ['Attachment' => false]);