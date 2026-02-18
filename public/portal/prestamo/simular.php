<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

$prestamoRepo = $container->get(
    App\Module\Prestamo\Repository\PrestamoRepository::class,
);

$categoriasTipoIngreso = $prestamoRepo->obtenerCategoriasTipoIngreso();

$renderer->render("./simular.latte", [
    "categoriasTipoIngreso" => $categoriasTipoIngreso,
]);
