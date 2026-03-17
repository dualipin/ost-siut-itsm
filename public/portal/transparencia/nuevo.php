<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Transparency\Application\UseCase\CreateTransparencyUseCase;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$renderer = $container->get(RendererInterface::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $useCase = $container->get(CreateTransparencyUseCase::class);

    try {
        $files = [];
        $links = [];
        
        if (isset($_POST['attachments']) && is_array($_POST['attachments'])) {
            foreach ($_POST['attachments'] as $index => $attPost) {
                $type = $attPost['type'] ?? '';
                $description = $attPost['description'] ?? null;

                if ($type === 'ENLACE') {
                    $links[] = [
                        'url' => $attPost['url'] ?? '',
                        'description' => $description
                    ];
                } else {
                    if (isset($_FILES['attachments_files']['error'][$index]) && $_FILES['attachments_files']['error'][$index] === UPLOAD_ERR_OK) {
                        $files[] = [
                            'name' => $_FILES['attachments_files']['name'][$index],
                            'type' => $_FILES['attachments_files']['type'][$index],
                            'tmp_name' => $_FILES['attachments_files']['tmp_name'][$index],
                            'error' => $_FILES['attachments_files']['error'][$index],
                            'attachment_type' => $type,
                            'description' => $description
                        ];
                    }
                }
            }
        }

        $transparency = $useCase->execute(
            authorId: (int) ($_SESSION['user_id'] ?? 1), // Asumiendo que el ID de sesión está en user_id, se debe adaptar a SessionInterface después
            title: $_POST['title'] ?? '',
            summary: $_POST['summary'] ?? null,
            typeValue: $_POST['type'] ?? '',
            datePublished: $_POST['date_published'] ?? '',
            isPrivate: isset($_POST['is_private']) && $_POST['is_private'] === '1',
            files: $files,
            links: $links
        );

        header('Location: ./listado.php?success=1');
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = "Ocurrió un error inesperado al guardar el documento.";
    }
}

$renderer->render("./nuevo.latte", [
    "error" => $error ?? null,
    "formData" => $_POST ?? []
]);
