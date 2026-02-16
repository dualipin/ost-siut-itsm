<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

$data = [
    "gestiones" => [],
];

$renderer->render("./gestiones.latte", $data);
