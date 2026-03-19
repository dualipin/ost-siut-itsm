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

$titulo = trim((string)($_POST['titulo'] ?? ''));
$contenido = trim((string)($_POST['contenido'] ?? ''));
$fecha_documento = trim((string)($_POST['fecha_documento'] ?? ''));
$privado = isset($_POST['privado']);

if ($titulo === '' || $contenido === '') {
    header('Location: index.php?error=' . urlencode('El título y contenido son obligatorios'));
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
    
    $files = (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) ? [$_FILES['archivo']] : [];
    
    $createUseCase->execute(
        authorId: $user->id,
        title: $titulo,
        summary: $contenido ?: null,
        typeValue: TransparencyType::TRAMITES->value,
        datePublished: $fecha_documento,
        isPrivate: $privado,
        files: $files
    );
    
    header('Location: index.php?mensaje=' . urlencode('Documento registrado con éxito'));
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Error al registrar: ' . $e->getMessage()));
    exit;
}