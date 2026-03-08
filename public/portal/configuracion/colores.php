<?php

use App\Bootstrap;
use App\Http\Response\Redirect;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Setting\Application\Command\UpdateColorCommand;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\Setting\Application\UseCase\UpdateColorUseCase;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$getColorUseCase = $container->get(GetColorUseCase::class);
$updateColorUseCase = $container->get(UpdateColorUseCase::class);

$colores = $getColorUseCase->execute();
$path = $_SERVER["REQUEST_URI"];

$redirector = $container->get(Redirect::class);
$renderer = $container->get(RendererInterface::class);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $command = UpdateColorCommand::fromRequestPayload($_POST);
        $updateColorUseCase->execute($command);
    } catch (Throwable $e) {
        $redirector
            ->to($path, [
                "error" => "Error al actualizar colores: " . $e->getMessage(),
            ])
            ->send();
    }

    $redirector
        ->to($path, [
            "mensaje" => "Colores actualizados correctamente.",
        ])
        ->send();
}

$mensaje = $_GET["mensaje"] ?? null;
$error = $_GET["error"] ?? null;

$renderer->render("./colores.latte", [
    "colores" => $colores,
    "mensaje" => $mensaje,
    "error" => $error,
]);
