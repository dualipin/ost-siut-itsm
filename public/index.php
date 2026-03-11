<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;

require __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

$renderer->render("./index.latte");
