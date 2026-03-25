<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Config\AppConfig;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$config = $container->get(AppConfig::class);
$filePath = rtrim($config->upload->privateDir, DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'informe-financiero-2025.pdf';

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    echo 'El informe financiero no esta disponible por el momento.';
    exit;
}

$mimeType = 'application/pdf';
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo !== false) {
    $detected = finfo_file($finfo, $filePath);
    if (is_string($detected) && $detected !== '') {
        $mimeType = $detected;
    }
    finfo_close($finfo);
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="informe-financiero-2025.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: private');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string) filesize($filePath));

readfile($filePath);
exit;
