<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\JsonResponse;
use App\Modules\Messaging\Application\UseCase\CreateContactMessageUseCase;
use App\Modules\Messaging\Domain\Exception\ContactMessageValidationException;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

/** @var CreateContactMessageUseCase $createContactMessageUseCase */
$createContactMessageUseCase = $container->get(CreateContactMessageUseCase::class);

$request = new FormRequest();

$asunto = $request->input("asunto");
$mensaje = $request->input("mensaje");
$correo = $request->input("correo");
$telefono = $request->input("telefono");
$nombre = $request->input("nombre");

try {
    $createContactMessageUseCase->execute(
        name: is_string($nombre) ? $nombre : "",
        email: is_string($correo) ? $correo : "",
        phone: is_string($telefono) ? $telefono : null,
        subject: is_string($asunto) ? $asunto : null,
        message: is_string($mensaje) ? $mensaje : "",
    );

    $response = JsonResponse::created([
        "tipo" => true,
        "message" =>
            "Mensaje recibido con éxito, nos pondremos en contacto contigo pronto.",
    ]);

    $response->send();
} catch (ContactMessageValidationException $exception) {
    JsonResponse::badRequest($exception->getMessage())->send();
} catch (\Throwable $th) {
    JsonResponse::serverError(
        "Ocurrió un error inesperado. Por favor intenta nuevamente más tarde.",
    )->send();
}
