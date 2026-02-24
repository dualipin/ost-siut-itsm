<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Request\JsonRequest;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Prestamo\Repository\PrestamoRepository;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

$repo = $container->get(PrestamoRepository::class);

$form = new FormRequest();

if ($form->method() == "POST") {
    $res = $form->input("descuentos_json");

    $descuentos = json_decode($res, true);
    var_dump($descuentos);
} else {
    $renderer->render("./simulador.latte", [
        "categoriasTipoIngreso" => $repo->obtenerCategoriasTipoIngreso(),
    ]);
}
