<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Publicacion\Entity\Publicacion;
use App\Module\Publicacion\Enum\TipoPublicacionEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

if (isset($_GET["detalle"]) && is_numeric($_GET["detalle"])) {
    $id = (int) $_GET["detalle"];
    // Aquí podrías cargar el aviso específico usando el ID
    // Por ejemplo:
    // $aviso = $publicacionRepository->findById($id);
    // Luego pasar el aviso a la vista
    // $data['aviso'] = $aviso;

    $data = [
        "publicacion" => new Publicacion(
            titulo: "Nuevo aviso importante",
            contenido: "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            imagenes: ["dadasd", "dasdad"],
            fechaPublicacion: new DateTimeImmutable(),
            tipo: TipoPublicacionEnum::Aviso,
        ),
    ];

    $renderer->render(__DIR__ . "/avisos.show.latte", $data);
} else {
    $data = [
        "avisos" => [],
    ];

    $renderer->render("./avisos.latte", $data);
}
