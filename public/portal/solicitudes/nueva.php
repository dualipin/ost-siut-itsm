<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Requests\Application\UseCase\CreateRequestUseCase;
use App\Modules\Requests\Application\UseCase\GetRequestTypesUseCase;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$renderer      = $container->get(RendererInterface::class);
$userContext   = $container->get(UserContextInterface::class);
$typesUseCase  = $container->get(GetRequestTypesUseCase::class);
$createUseCase = $container->get(CreateRequestUseCase::class);

$authUser = $userContext->get();
$error    = null;
$success  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requestTypeId = (int)($_POST['request_type_id'] ?? 0);
        $reason        = trim($_POST['reason'] ?? '');

        if ($requestTypeId === 0) {
            throw new InvalidArgumentException('Selecciona un tipo de solicitud.');
        }

        $uploadedFiles = [];
        $uploadDir     = dirname(__DIR__, 3) . '/uploads/solicitudes/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        if (!empty($_FILES['attachments']['name'][0])) {
            $allowedMimes = [
                'image/jpeg', 'image/png', 'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmpName) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $mime = mime_content_type($tmpName);
                if (!in_array($mime, $allowedMimes, true)) {
                    throw new InvalidArgumentException('Tipo de archivo no permitido.');
                }

                $ext      = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
                $fileName = uniqid('sol_', true) . '.' . strtolower($ext);
                $dest     = $uploadDir . $fileName;
                move_uploaded_file($tmpName, $dest);

                $uploadedFiles[] = [
                    'path'        => 'uploads/solicitudes/' . $fileName,
                    'mime'        => $mime,
                    'description' => $_FILES['attachments']['name'][$i],
                ];
            }
        }

        $folio   = $createUseCase->execute($authUser->id, $requestTypeId, $reason, $uploadedFiles);
        $success = "Solicitud registrada exitosamente con folio <strong>{$folio}</strong>.";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$data = $typesUseCase->execute();

// Agregamos un mapa de descripciones para que Alpine pueda mostrarlas en tiempo real
$descriptionsMap = [];
foreach ($data['types'] as $type) {
    if ($type->description) {
        $descriptionsMap[$type->requestTypeId] = $type->description;
    }
}

$data['descriptionsMap'] = $descriptionsMap;
$data['error']    = $error;
$data['success']  = $success;
$data['authUser'] = $authUser;

$renderer->render(__DIR__ . '/../../../templates/solicitudes/nueva.latte', $data);
