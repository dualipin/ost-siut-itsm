<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Module\Mensajeria\DTO\CrearMensajeExternoDTO;
use App\Module\Mensajeria\Enum\MessagePriorityEnum;
use App\Module\Mensajeria\Enum\MessageTypeEnum;
use App\Module\Mensajeria\Repository\MensajeRepository;
use App\Module\Mensajeria\Service\ContactoGeneralService;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$service = $container->get(ContactoGeneralService::class);

$request = new FormRequest();

$asunto = $request->input("asunto");
$mensaje = $request->input("mensaje");
$correo = $request->input("correo");
$telefono = $request->input("telefono");
$nombre = $request->input("nombre");

try {
    $service->enviarMensaje(
        new CrearMensajeExternoDTO(
            asunto: $asunto,
            nombreCompleto: $nombre,
            correo: $correo,
            telefono: $telefono,
            mensaje: $mensaje,
            tipo: MessageTypeEnum::ContactoGeneral,
            prioridad: MessagePriorityEnum::Media,
        ),
    );

    $response = JsonResponse::created([
        "tipo" => "exito",
        "message" =>
            "Mensaje recibido con éxito, nos pondremos en contacto contigo pronto.",
    ]);

    $response->send();
} catch (\InvalidArgumentException $e) {
    // Error de validación (400 Bad Request)
    $response = JsonResponse::badRequest($e->getMessage());
    $response->send();
} catch (\PDOException $e) {
    // Error de base de datos (500 Internal Server Error)
    $response = JsonResponse::serverError(
        "Ocurrió un error al procesar tu mensaje. Por favor intenta nuevamente más tarde.",
    );
    $response->send();
} catch (\Throwable $th) {
    // Cualquier otro error inesperado (500)
    $response = JsonResponse::serverError(
        "Ocurrió un error inesperado. Por favor intenta nuevamente más tarde.",
    );
    $response->send();
}
