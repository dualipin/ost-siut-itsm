<?php

use App\Bootstrap;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../bootstrap.php';

$container = Bootstrap::buildContainer();
$logger = $container->get(LoggerInterface::class);

try {
    // Obtener el parámetro de ruta
    $path = trim($_GET['path'] ?? '');

    if (empty($path)) {
        throw new RuntimeException('Parámetro de archivo no especificado');
    }

    // Evitar path traversal attacks
    $realPath = realpath(__DIR__ . '/../' . $path);
    $uploadsDir = realpath(__DIR__ . '/../uploads');

    if (!$uploadsDir) {
        throw new RuntimeException('Upload directory not configured');
    }

    if (!$realPath || !str_starts_with($realPath, $uploadsDir)) {
        throw new RuntimeException('Acceso no autorizado al archivo');
    }

    if (!file_exists($realPath) || !is_file($realPath)) {
        throw new RuntimeException('El archivo no existe');
    }

    // Log de descarga
    $logger->info('Archivo descargado', [
        'path' => $path,
        'file' => basename($realPath),
        'size' => filesize($realPath)
    ]);

    // Servir el archivo
    header('Content-Type: ' . (mime_content_type($realPath) ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($realPath));
    header('Content-Disposition: attachment; filename="' . basename($realPath) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($realPath);
    exit;

} catch (Exception $e) {
    $logger->error('Error descargando archivo', [
        'error' => $e->getMessage(),
        'path' => $_GET['path'] ?? '',
    ]);
    
    http_response_code(404);
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Archivo no disponible</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; color: #333; }
        .container { max-width: 500px; margin: 50px auto; text-align: center; }
        h1 { font-size: 24px; margin-bottom: 10px; }
        p { color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1>Archivo no disponible</h1>
    <p>El archivo solicitado no pudo ser accedido.</p>
</div>
</body>
</html>';
}
