<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
} else {
    $renderer = $container->get(RendererInterface::class);

    $data = [];

    $renderer->render("./recuperar-contra.latte");
}
