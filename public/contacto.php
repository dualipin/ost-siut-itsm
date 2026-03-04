<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Module\Mensajeria\DTO\CrearMensajeExternoDTO;
use App\Module\Mensajeria\Enum\PrioridadMensajeEnum;
use App\Module\Mensajeria\Enum\TipoMensajeEnum;
use App\Module\Mensajeria\Repository\MensajeRepository;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$repo = $container->get(MensajeRepository::class);

$request = new FormRequest();

$asunto = $request->input("asunto");
$mensaje = $request->input("mensaje");
$correo = $request->input("correo");
$telefono = $request->input("telefono");
$nombre = $request->input("nombre");

try {
    $repo->registrarMensajeExterno(
        new CrearMensajeExternoDTO(
            asunto: $asunto,
            nombreCompleto: $nombre,
            correo: $correo,
            telefono: $telefono,
            mensaje: $mensaje,
            tipo: TipoMensajeEnum::ContactoGeneral,
            prioridad: PrioridadMensajeEnum::Media,
        ),
    );

    $response = JsonResponse::created([
        "tipo" => "exito",
        "message" =>
            "Mensaje recibido con éxito, nos pondremos en contacto contigo pronto.",
    ]);

    $response->send();
} catch (\Throwable $th) {
    $response = JsonResponse::serverError(
        "Ocurrió un error al enviar tu mensaje, por favor intenta nuevamente más tarde.",
    );

    $response->send();
}
