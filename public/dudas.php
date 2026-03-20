<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Messaging\Application\UseCase\CreateQuestionUseCase;
use App\Modules\Messaging\Domain\Exception\QuestionValidationException;

require_once __DIR__ . "/../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$useCase = $container->get(CreateQuestionUseCase::class);

$success = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attachments = [];
    if (!empty($_FILES['adjuntos']['name'][0])) {
        foreach ($_FILES['adjuntos']['name'] as $i => $name) {
            $attachments[] = [
                'name'     => $name,
                'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
                'size'     => $_FILES['adjuntos']['size'][$i],
                'type'     => $_FILES['adjuntos']['type'][$i],
                'error'    => $_FILES['adjuntos']['error'][$i],
            ];
        }
    }

    try {
        $useCase->execute(
            name: $_POST["nombre"] ?? "",
            email: $_POST["correo"] ?? "",
            question: $_POST["mensaje"] ?? "",
            attachments: $attachments,
        );
        $success = "Tu duda ha sido enviada correctamente. Te responderemos a la brevedad.";
    } catch (QuestionValidationException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = "Ocurrió un error inesperado al enviar tu duda. Por favor, inténtalo de nuevo más tarde.";
    }
}

$renderer->render("./dudas.latte", [
    "success" => $success,
    "error" => $error,
]);
