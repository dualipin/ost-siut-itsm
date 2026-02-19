<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Usuario\DTO\UsuarioSimpleDTO;
use App\Module\Usuario\Repository\UsuarioRepository;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$usuarios = $container->get(UsuarioRepository::class);

$renderer->render("./listado.latte", [
    "usuarios" => $usuarios->listado(),
    // "usuarios" => [
    //     new UsuarioSimpleDTO(
    //         id: "1",
    //         nombre: "Juan",
    //         apellidos: "Pérez",
    //         email: "correo@correo.com",
    //         rol: \App\Module\Usuario\Entity\RolEnum::Admin,
    //         activo: true,
    //         departamento: "dasdasd",
    //     ),
    //     new UsuarioSimpleDTO(
    //         id: "2",
    //         nombre: "Juan adasd",
    //         apellidos: "Pérez",
    //         email: "correo@correo.com",
    //         rol: \App\Module\Usuario\Entity\RolEnum::Finanzas,
    //         activo: true,
    //     ),
    //     new UsuarioSimpleDTO(
    //         id: "3",
    //         nombre: "Juandasdd",
    //         apellidos: "Pérez",
    //         email: "correo@correo.com",
    //         rol: \App\Module\Usuario\Entity\RolEnum::Agremiado,
    //         activo: true,
    //     ),
    // ],
]);
