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
    try {
        $useCase->execute(
            name: $_POST["nombre"] ?? "",
            email: $_POST["correo"] ?? "",
            question: $_POST["mensaje"] ?? "",
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
