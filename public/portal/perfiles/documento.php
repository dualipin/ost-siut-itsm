<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Config\AppConfig;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$userProvider = $container->get(UserProviderInterface::class);
$user = $userProvider->get();

if ($user === null) {
    http_response_code(401);
    exit;
}

$documentTypeInput = is_string($_GET["tipo"] ?? null) ? $_GET["tipo"] : "";
$documentType = DocumentTypeEnum::tryFrom($documentTypeInput);

if ($documentType === null) {
    http_response_code(404);
    exit;
}

$userRepository = $container->get(UserRepositoryInterface::class);
$profileUser = $userRepository->findById($user->id);

if ($profileUser === null) {
    http_response_code(404);
    exit;
}

if (!in_array($documentType, resolveAllowedDocumentTypes($profileUser->role), true)) {
    http_response_code(403);
    exit;
}

$documents = $userRepository->findDocumentsByUserId($user->id);
$relativePath = normalizeUploadPath($documents[$documentType->value] ?? null);

if ($relativePath === "") {
    http_response_code(404);
    exit;
}

$appConfig = $container->get(AppConfig::class);
$privateRoot = rtrim($appConfig->upload->privateDir, DIRECTORY_SEPARATOR);
$publicRoot = rtrim($appConfig->upload->publicDir, DIRECTORY_SEPARATOR);

$filePath = resolveSafeFilePath($privateRoot, $relativePath);

// Compatibilidad con documentos antiguos guardados en carpeta pública.
if ($filePath === null) {
    $filePath = resolveSafeFilePath($publicRoot, $relativePath);
}

if ($filePath === null) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string) ($finfo->file($filePath) ?: "application/octet-stream");
$disposition = str_starts_with($mimeType, "image/") || $mimeType === "application/pdf"
    ? "inline"
    : "attachment";

header("Content-Type: {$mimeType}");
header("Content-Length: " . filesize($filePath));
header(
    "Content-Disposition: " .
        $disposition .
        '; filename="' . basename($filePath) . '"',
);

readfile($filePath);
exit;

/**
 * @return array<int, DocumentTypeEnum>
 */
function resolveAllowedDocumentTypes(RoleEnum $role): array
{
    if ($role === RoleEnum::NoAgremiado) {
        return [
            DocumentTypeEnum::Ine,
            DocumentTypeEnum::ComprobanteDomicilio,
            DocumentTypeEnum::Curp,
        ];
    }

    return [
        DocumentTypeEnum::Afiliacion,
        DocumentTypeEnum::ComprobanteDomicilio,
        DocumentTypeEnum::Ine,
        DocumentTypeEnum::ComprobantePago,
        DocumentTypeEnum::Curp,
    ];
}

function normalizeUploadPath(?string $value): string
{
    $path = ltrim((string) ($value ?? ""), "/\\");

    if ($path === "") {
        return "";
    }

    if (str_starts_with($path, "uploads/")) {
        return substr($path, 8);
    }

    return $path;
}

function resolveSafeFilePath(string $baseDir, string $relativePath): ?string
{
    $normalizedRelativePath = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, ltrim($relativePath, "/\\"));
    $candidatePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalizedRelativePath;

    $realBaseDir = realpath($baseDir);
    $realCandidatePath = realpath($candidatePath);

    if ($realBaseDir === false || $realCandidatePath === false) {
        return null;
    }

    if (!str_starts_with($realCandidatePath, $realBaseDir . DIRECTORY_SEPARATOR)) {
        return null;
    }

    if (!is_file($realCandidatePath) || !is_readable($realCandidatePath)) {
        return null;
    }

    return $realCandidatePath;
}
