<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\GetTransparencyUseCase;
use App\Modules\Transparency\Application\UseCase\UpdateTransparencyUseCase;
use App\Modules\Transparency\Domain\Exception\TransparencyNotFoundException;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);
$getUseCase = $container->get(GetTransparencyUseCase::class);

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id === 0) {
    header("Location: ./listado.php");
    exit;
}

try {
    $transparency = $getUseCase->execute($id);
} catch (TransparencyNotFoundException $e) {
    header("Location: ./listado.php?error=notfound");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateUseCase = $container->get(UpdateTransparencyUseCase::class);

    try {
        $transparency = $updateUseCase->execute(
            id: $id,
            title: $_POST['title'] ?? '',
            summary: $_POST['summary'] ?? null,
            typeValue: $_POST['type'] ?? '',
            datePublished: $_POST['date_published'] ?? '',
            isPrivate: isset($_POST['is_private']) && $_POST['is_private'] === '1'
        );

        header("Location: ./listado.php?updated=1");
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = "Ocurrió un error inesperado al actualizar el documento.";
    }
}

$renderer->render("./editar.latte", [
    "transparency" => $transparency,
    "error" => $error ?? null,
    "formData" => $_POST ?? []
]);
