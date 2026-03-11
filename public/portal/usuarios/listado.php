<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$usuarios = $container->get(UserRepositoryInterface::class);

$renderer->render("./listado.latte", [
    "usuarios" => $usuarios->listado(),
]);
