<?php

declare(strict_types=1);

use App\Infrastructure\Config\AppConfig;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\CashBoxes\Domain\Exception\AnnualFinancialReportValidationException;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Security\AuthenticatedUser;
use Psr\Container\ContainerInterface;

function requireAnnualReportsAccess(ContainerInterface $container, bool $manage = false): AuthenticatedUser
{
    $runner = $container->get(MiddlewareRunner::class);
    $middlewareFactory = $container->get(MiddlewareFactory::class);

    $runner->runOrRedirect(
        $manage
            ? $middlewareFactory->role(RoleEnum::Admin, RoleEnum::Lider)
            : $middlewareFactory->auth()
    );

    $user = $container->get(UserContextInterface::class)->get();

    if ($user === null) {
        http_response_code(401);
        exit;
    }

    return $user;
}

function storeAnnualReportDocument(array $file, AppConfig $config, int $year): string
{
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new AnnualFinancialReportValidationException('Debes seleccionar un archivo válido.');
    }

    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new AnnualFinancialReportValidationException('No se pudo procesar el archivo cargado.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === '') {
        throw new AnnualFinancialReportValidationException('El archivo debe tener una extensión válida.');
    }

    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new AnnualFinancialReportValidationException('El tipo de archivo no es compatible.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 20 * 1024 * 1024) {
        throw new AnnualFinancialReportValidationException('El archivo debe pesar menos de 20 MB.');
    }

    $directory = rtrim($config->upload->privateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'informes-financieros';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new AnnualFinancialReportValidationException('No se pudo preparar la carpeta de almacenamiento.');
    }

    $fileName = sprintf('%d-%s.%s', $year, bin2hex(random_bytes(8)), $extension);
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new AnnualFinancialReportValidationException('No se pudo guardar el archivo.');
    }

    return 'uploads/informes-financieros/' . $fileName;
}

function deleteStoredAnnualReportDocument(?string $storedPath, AppConfig $config): void
{
    $absolutePath = resolveAnnualReportAbsolutePath($storedPath, $config);

    if ($absolutePath !== null && is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function resolveAnnualReportAbsolutePath(?string $storedPath, AppConfig $config): ?string
{
    if ($storedPath === null || trim($storedPath) === '') {
        return null;
    }

    $normalizedPath = str_replace('\\', '/', trim($storedPath));
    $normalizedPath = ltrim($normalizedPath, '/');

    if (str_starts_with($normalizedPath, 'uploads/')) {
        $normalizedPath = substr($normalizedPath, strlen('uploads/'));
    }

    return rtrim($config->upload->privateDir, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
}