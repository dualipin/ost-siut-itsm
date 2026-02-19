<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Prestamo\Repository\PrestamoRepository;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

$prestamoRepo = $container->get(PrestamoRepository::class);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $monto = $_POST["monto"];
    $plazo = $_POST["plazo"];
    $unidad = $_POST["unidad"];
    $categoriaTipoIngreso = $_POST["categoriaTipoIngreso"];
}

$categoriasTipoIngreso = $prestamoRepo->obtenerCategoriasTipoIngreso();

$renderer->render("./simular.latte", [
    "categoriasTipoIngreso" => $categoriasTipoIngreso,
]);
