<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Response\Redirector;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Utils\DocumentHelper;

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

// allow admins/leaders to inspect another user's profile via ?uid=123
$ownerId = null;
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
    $ownerId = (int) $_GET['uid'];
}

if ($ownerId === null || $ownerId === 0) {
    $ownerId = $user->id;
}

if ($ownerId !== $user->id && !\App\Shared\Utils\DocumentHelper::canViewOtherUserDocuments($user->role)) {
    http_response_code(403);
    exit;
}

$userRepository = $container->get(UserRepositoryInterface::class);
$redirector = $container->get(Redirector::class);
$requestPath = $_SERVER["REQUEST_URI"] ?? "/portal/perfiles/ver.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // only owner can submit changes
    if ($ownerId !== $user->id) {
        http_response_code(403);
        exit;
    }

    try {
        $profileUser = $userRepository->findById($user->id);

        if ($profileUser === null) {
            throw new RuntimeException("No se encontro el usuario autenticado.");
        }

        $appConfig = $container->get(AppConfig::class);
        $publicUploadRootDir = rtrim($appConfig->upload->publicDir, DIRECTORY_SEPARATOR);
        $privateUploadRootDir = rtrim($appConfig->upload->privateDir, DIRECTORY_SEPARATOR);
        $userRelativeDir = "users/" . $user->id;
        $publicUserAbsoluteDir =
            $publicUploadRootDir .
            DIRECTORY_SEPARATOR .
            "users" .
            DIRECTORY_SEPARATOR .
            $user->id;
        $privateUserAbsoluteDir =
            $privateUploadRootDir .
            DIRECTORY_SEPARATOR .
            "users" .
            DIRECTORY_SEPARATOR .
            $user->id;

        foreach ([$publicUserAbsoluteDir, $privateUserAbsoluteDir] as $uploadDir) {
            if (!is_dir($uploadDir) &&
                !mkdir($uploadDir, 0775, true) &&
                !is_dir($uploadDir)) {
                throw new RuntimeException("No se pudo crear el directorio de carga.");
            }
        }

        $photoPath = uploadProfileFile(
            $_FILES["fotoPerfil"] ?? null,
            $publicUserAbsoluteDir,
            $userRelativeDir,
            ["image/jpeg", "image/png", "image/webp"],
            2 * 1024 * 1024,
            "perfil",
        );

        $addressInput = normalizeNullableString(
            is_string($_POST["direccion"] ?? null)
                ? $_POST["direccion"]
                : null,
        );
        $phoneInput = normalizeNullableString(
            is_string($_POST["telefono"] ?? null)
                ? $_POST["telefono"]
                : null,
        );
        $emailInput = normalizeNullableString(
            is_string($_POST["correo"] ?? null)
                ? $_POST["correo"]
                : null,
        );

        $userRepository->updateProfile(
            userId: $user->id,
            address: $addressInput,
            phone: $phoneInput,
            email: $emailInput ?? $profileUser->email,
            photoPath: $photoPath,
            curp: $profileUser->personalInfo->curp,
        );

        $allowedDocumentTypes = DocumentHelper::resolveAllowedDocumentTypes($profileUser->role);

        foreach ($allowedDocumentTypes as $documentType) {
            $fieldName = $documentType->value;

            $documentPath = uploadProfileFile(
                $_FILES[$fieldName] ?? null,
                $privateUserAbsoluteDir,
                $userRelativeDir,
                ["application/pdf", "image/jpeg", "image/png", "image/webp"],
                5 * 1024 * 1024,
                $fieldName,
            );

            if ($documentPath === null) {
                continue;
            }

            $userRepository->upsertDocument($user->id, $documentType, $documentPath);
        }

        $redirector
            ->to($requestPath, ["mensaje" => "Perfil actualizado correctamente."])
            ->send();
    } catch (Throwable $exception) {
        $redirector
            ->to($requestPath, ["error" => "No fue posible actualizar tu perfil."])
            ->send();
    }
} // end POST handling

$profileUser = $userRepository->findById($ownerId);

if ($profileUser === null) {
    http_response_code(404);
    exit;
}

$perfil = [
    "id" => $profileUser->id,
    "nombre" => $profileUser?->personalInfo->name ?? $user->name,
    "apellidos" => $profileUser?->personalInfo->surnames ?? "",
    "curp" => $profileUser?->personalInfo->curp ?? "",
    "direccion" => $profileUser?->personalInfo->address ?? "",
    "telefono" => $profileUser?->personalInfo->phone ?? "",
    "correo" => $profileUser?->email ?? $user->email,
    "nss" => $profileUser?->workData->nss ?? "",
    "categoria" => $profileUser?->workData->category,
    "departamento" => $profileUser?->workData->department,
    "rol" => $profileUser?->role->value ?? $user->role->value,
    "fecha_nacimiento" => $profileUser?->personalInfo->birthdate?->format("Y-m-d"),
    "fecha_ingreso" => $profileUser?->workData->workStartDate?->format("Y-m-d"),
];

$docs = [
    "perfil" => DocumentHelper::normalizeUploadPath($profileUser->personalInfo->photo),
    "afiliacion" => "",
    "comprobante_domicilio" => "",
    "ine" => "",
    "comprobante_pago" => "",
    "curp" => "",
];

$storedDocuments = $userRepository->findDocumentsByUserId($ownerId);
$docStatuses = $userRepository->findDocumentStatusesByUserId($ownerId);

foreach ($storedDocuments as $documentType => $documentPath) {
    $storedDocuments[$documentType] = DocumentHelper::normalizeUploadPath($documentPath);
}

$docs = array_merge($docs, $storedDocuments);
$documentFields = DocumentHelper::resolveDocumentFieldsByRole($profileUser->role);
$hasAnyDocument = false;

foreach ($documentFields as $field) {
    if (!empty($docs[$field["key"]] ?? "")) {
        $hasAnyDocument = true;
        break;
    }
}

$mensaje = is_string($_GET["mensaje"] ?? null) ? $_GET["mensaje"] : null;
$error = is_string($_GET["error"] ?? null) ? $_GET["error"] : null;

$renderer = $container->get(RendererInterface::class);

$renderer->render(__DIR__ . "/ver.latte", [
	"authUser" => $user,
	"perfil" => $perfil,
	"docs" => $docs,
    "docStatuses" => $docStatuses,
	"documentFields" => $documentFields,
	"hasAnyDocument" => $hasAnyDocument,
	"mensaje" => $mensaje,
	"error" => $error,
]);

/**
 * @return array<int, array{key: string, label: string}>
 */
function normalizeNullableString(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = trim($value);

    return $normalized === "" ? null : $normalized;
}

function uploadProfileFile(
	?array $file,
	string $absoluteDir,
	string $relativeDir,
	array $allowedMimeTypes,
	int $maxSizeInBytes,
	string $prefix,
): ?string {
	if ($file === null) {
		return null;
	}

	$error = (int) ($file["error"] ?? UPLOAD_ERR_NO_FILE);

	if ($error === UPLOAD_ERR_NO_FILE) {
		return null;
	}

	if ($error !== UPLOAD_ERR_OK) {
		throw new RuntimeException("Error al subir el archivo.");
	}

	$tmpName = (string) ($file["tmp_name"] ?? "");
	$originalName = (string) ($file["name"] ?? "archivo");
	$size = (int) ($file["size"] ?? 0);

	if ($tmpName === "" || !is_uploaded_file($tmpName)) {
		throw new RuntimeException("No se detecto un archivo valido.");
	}

	if ($size <= 0 || $size > $maxSizeInBytes) {
		throw new RuntimeException("El archivo supera el limite permitido.");
	}

	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mimeType = (string) $finfo->file($tmpName);

	if (!in_array($mimeType, $allowedMimeTypes, true)) {
		throw new RuntimeException("Tipo de archivo no permitido.");
	}

	$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

	if ($extension === "") {
		$extension = match ($mimeType) {
			"image/jpeg" => "jpg",
			"image/png" => "png",
			"image/webp" => "webp",
			"application/pdf" => "pdf",
			default => "bin",
		};
	}

	$safePrefix = preg_replace('/[^a-z0-9_\-]/i', "", $prefix) ?: "archivo";
	$fileName = $safePrefix . "_" . bin2hex(random_bytes(8)) . "." . $extension;
	$targetPath = rtrim($absoluteDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

	if (!move_uploaded_file($tmpName, $targetPath)) {
		throw new RuntimeException("No se pudo guardar el archivo.");
	}

	return trim($relativeDir, "/\\") . "/" . $fileName;
}
