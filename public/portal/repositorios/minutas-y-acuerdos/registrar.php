<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Shared\Context\UserProviderInterface;
use App\Modules\Transparency\Application\UseCase\CreateTransparencyUseCase;
use App\Modules\Transparency\Domain\Enum\TransparencyType;

require_once __DIR__ . '/../../../../bootstrap.php';

$container = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: index.php');
    exit('Método no permitido');
}

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

if (!$user || ($user->role->value !== 'administrador' && $user->role->value !== 'lider')) {
    header('Location: index.php?error=' . urlencode('Permisos insuficientes'));
    exit;
}

$titulo = trim((string)($_POST['title'] ?? ''));
$contenido = trim((string)($_POST['summary'] ?? ''));
$fecha_documento = trim((string)($_POST['date_published'] ?? ''));
$privado = isset($_POST['is_private']);

if ($titulo === '') {
    header('Location: index.php?error=' . urlencode('El título es obligatorio'));
    exit;
}

// Convert YYYY-MM-DD or use fallback. The UseCase expects YYYY-MM-DD.
if ($fecha_documento !== '') {
    $fechaObj = date_create_from_format('Y-m-d', $fecha_documento);
    if (!$fechaObj) {
        header('Location: index.php?error=' . urlencode('Fecha inválida'));
        exit;
    }
} else {
    $fecha_documento = date('Y-m-d');
}

try {
    $createUseCase = $container->get(CreateTransparencyUseCase::class);

    $files = [];
    $links = [];

    if (isset($_POST['attachments']) && is_array($_POST['attachments'])) {
        foreach ($_POST['attachments'] as $index => $attPost) {
            $type = $attPost['type'] ?? '';
            $description = $attPost['description'] ?? null;

            if ($type === 'ENLACE') {
                $links[] = [
                    'url' => $attPost['url'] ?? '',
                    'description' => $description,
                ];
                continue;
            }

            if (isset($_FILES['attachments_files']['error'][$index]) && $_FILES['attachments_files']['error'][$index] === UPLOAD_ERR_OK) {
                $files[] = [
                    'name' => $_FILES['attachments_files']['name'][$index],
                    'type' => $_FILES['attachments_files']['type'][$index],
                    'tmp_name' => $_FILES['attachments_files']['tmp_name'][$index],
                    'error' => $_FILES['attachments_files']['error'][$index],
                    'attachment_type' => $type,
                    'description' => $description,
                ];
            }
        }
    }
    
    $createUseCase->execute(
        authorId: $user->id,
        title: $titulo,
        summary: $contenido !== '' ? $contenido : null,
        typeValue: TransparencyType::MINUTAS->value,
        datePublished: $fecha_documento,
        isPrivate: $privado,
        files: $files,
        links: $links
    );
    
    header('Location: index.php?mensaje=' . urlencode('Documento registrado con éxito'));
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Error al registrar: ' . $e->getMessage()));
    exit;
}