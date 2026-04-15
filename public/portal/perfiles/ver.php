<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Response\Redirector;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Setting\Application\UseCase\GetColorUseCase;
use App\Modules\User\Domain\Enum\DocumentTypeEnum;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\CredentialCardHelper;
use App\Shared\Utils\DocumentHelper;
use Dompdf\Dompdf;
use chillerlan\QRCode\QRCode;

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
            if (
                !is_dir($uploadDir) &&
                !mkdir($uploadDir, 0775, true) &&
                !is_dir($uploadDir)
            ) {
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

$credentialMissingRequirements = CredentialCardHelper::resolveMissingRequirements($profileUser);
$canGenerateCredential = CredentialCardHelper::canGenerate($profileUser);

if (isset($_GET['credencial']) && $_GET['credencial'] === '1') {
    if ($ownerId !== $user->id) {
        http_response_code(403);
        exit;
    }

    if (!$canGenerateCredential) {
        $missingText = implode(', ', $credentialMissingRequirements);
        $message = 'No es posible generar la credencial.';

        if ($missingText !== '') {
            $message .= ' Completa los siguientes datos: ' . $missingText . '.';
        }

        $redirector
            ->to($requestPath, ['error' => $message])
            ->send();

        exit;
    }

    streamUserIdentificationCard($container, $userRepository, $profileUser);
}

if (isset($_GET['constancia']) && $_GET['constancia'] === '1') {
    streamProfileRegistrationCertificate($container, $profileUser);
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
    "canGenerateCredential" => $canGenerateCredential,
    "credentialMissingRequirements" => $credentialMissingRequirements,
    "mensaje" => $mensaje,
    "error" => $error,
]);

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

function streamProfileRegistrationCertificate($container, $profileUser): never
{
    /** @var RendererInterface $renderer */
    $renderer = $container->get(RendererInterface::class);
    /** @var Dompdf $pdf */
    $pdf = $container->get(Dompdf::class);

    $logoSrc = resolvePdfLogoDataUri(__DIR__ . "/../../assets/images");

    $primaryColor = "#611232";

    try {
        $colorConfig = $container->get(GetColorUseCase::class)->execute();

        if ($colorConfig !== null && $colorConfig->primary !== "") {
            $primaryColor = $colorConfig->primary;
        }
    } catch (Throwable) {
        // Mantener color institucional por defecto si falla la carga de configuración.
    }

    $userData = [
        "name" => $profileUser->personalInfo->name,
        "surnames" => $profileUser->personalInfo->surnames,
        "address" => $profileUser->personalInfo->address ?? "No disponible",
        "phone" => $profileUser->personalInfo->phone ?? "No disponible",
        "email" => $profileUser->email,
        "category" => $profileUser->workData->category ?? "No disponible",
        "department" => $profileUser->workData->department ?? "No disponible",
        "nss" => $profileUser->workData->nss ?? "No disponible",
        "curp" => $profileUser->personalInfo->curp ?? "No disponible",
        "birthdate" => $profileUser->personalInfo->birthdate?->format("Y-m-d") ?? "No disponible",
        "work_start_date" => $profileUser->workData->workStartDate?->format("Y-m-d") ?? "No disponible",
        "role" => $profileUser->role->value,
    ];

    $html = $renderer->renderToString(
        __DIR__ . "/../../../templates/documents/agremiado-registration-certificate.latte",
        [
            "user" => $userData,
            "issuedAt" => (new DateTimeImmutable())->format("d/m/Y H:i"),
            "logoSrc" => $logoSrc,
            "primaryColor" => $primaryColor,
        ],
    );

    // Dompdf en este proyecto se inicializa con recursos remotos deshabilitados;
    // habilitamos aquí para asegurar render correcto de data URI en el logo.
    $options = $pdf->getOptions();
    $options->setIsRemoteEnabled(true);
    $pdf->setOptions($options);

    $pdf->loadHtml($html);
    $pdf->render();

    $safeName = preg_replace('/[^a-z0-9]+/i', '-', trim($profileUser->personalInfo->name . ' ' . $profileUser->personalInfo->surnames));
    $safeName = $safeName !== null && $safeName !== '' ? trim($safeName, '-') : 'agremiado';

    $filename = "constancia-" . strtolower($safeName) . "-" . date("YmdHis") . ".pdf";

    $pdf->stream($filename, ["Attachment" => true]);

    exit;
}

function streamUserIdentificationCard(
    $container,
    UserRepositoryInterface $userRepository,
    $profileUser,
): never {
    /** @var RendererInterface $renderer */
    $renderer = $container->get(RendererInterface::class);
    /** @var Dompdf $pdf */
    $pdf = $container->get(Dompdf::class);
    /** @var AppConfig $appConfig */
    $appConfig = $container->get(AppConfig::class);

    $verificationUrl = CredentialCardHelper::buildVerificationUrl($appConfig->baseUrl, $profileUser);
    $vigencia = CredentialCardHelper::resolveVigencia($profileUser);
    $membershipLabel = $vigencia === 'VIGENTE'
        ? 'Miembro activo del padron'
        : 'Membresia no vigente';
    $theme = resolveCredentialCardTheme($container);

    $qrDataUri = null;

    try {
        $qrDataUri = (new QRCode())->render($verificationUrl);
    } catch (Throwable) {
        // Si la libreria QR falla, se mantiene visible la URL de validacion.
    }

    $logos = resolveCredentialLogosDataUri(__DIR__ . '/../../assets/images/logo');
    $photoDataUri = resolveUserPhotoDataUri($appConfig, $profileUser->personalInfo->photo);
    $signatory = resolveLeaderSignatory($userRepository);

    $html = $renderer->renderToString(
        __DIR__ . '/../../../templates/documents/agremiado-id-card.latte',
        [
            'holder' => [
                'fullName' => trim($profileUser->personalInfo->name . ' ' . $profileUser->personalInfo->surnames),
                'cargo' => trim((string) $profileUser->workData->category) !== ''
                    ? $profileUser->workData->category
                    : 'No disponible',
                'imss' => trim((string) $profileUser->workData->nss) !== ''
                    ? $profileUser->workData->nss
                    : 'No disponible',
                'photoSrc' => $photoDataUri,
                'vigencia' => $vigencia,
                'membershipLabel' => $membershipLabel,
                'isActive' => $profileUser->active,
            ],
            'signatory' => $signatory,
            'verificationUrl' => $verificationUrl,
            'qrSrc' => $qrDataUri,
            'issuedAt' => (new DateTimeImmutable())->format('d/m/Y H:i'),
            'website' => 'https://siutitsm.com.mx',
            'organization' => 'Sindicato Unico de Trabajadores del Instituto Tecnologico Superior de Macuspana (SUTITSM)',
            'address' => 'Av. Tecnologico S/N, Lerdo de Tejada 1ra. Seccion, Macuspana, Tabasco, C.P. 86719.',
            'rfc' => 'SUT191121324',
            'logos' => $logos,
            'theme' => $theme,
        ],
    );

    $options = $pdf->getOptions();
    $options->setIsRemoteEnabled(true);
    $pdf->setOptions($options);
    $pdf->setPaper('letter', 'portrait');
    $pdf->loadHtml($html);
    $pdf->render();

    $safeName = preg_replace('/[^a-z0-9]+/i', '-', trim($profileUser->personalInfo->name . ' ' . $profileUser->personalInfo->surnames));
    $safeName = $safeName !== null && $safeName !== '' ? trim($safeName, '-') : 'agremiado';

    $filename = 'credencial-' . strtolower($safeName) . '-' . date('YmdHis') . '.pdf';

    $pdf->stream($filename, ['Attachment' => false]);

    exit;
}

function resolvePdfLogoDataUri(string $imagesDir): ?string
{
    $candidates = [
        ["file" => "logo.jpg", "mime" => "image/jpeg"],
        ["file" => "logo.jpeg", "mime" => "image/jpeg"],
        ["file" => "logo.png", "mime" => "image/png"],
        ["file" => "logo.webp", "mime" => "image/webp"],
    ];

    foreach ($candidates as $candidate) {
        $path = rtrim($imagesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate["file"];

        if (!is_file($path)) {
            continue;
        }

        $data = file_get_contents($path);

        if (!is_string($data) || $data === "") {
            continue;
        }

        return "data:" . $candidate["mime"] . ";base64," . base64_encode($data);
    }

    return null;
}

/**
 * @return array{tecnm: ?string, itsm: ?string, siut: ?string}
 */
function resolveCredentialLogosDataUri(string $logosDir): array
{
    return [
        'tecnm' => resolveImageDataUriByCandidates($logosDir, ['logo-tecnm.png']),
        'itsm' => resolveImageDataUriByCandidates($logosDir, ['logo-itsm.jpg']),
        'siut' => resolveImageDataUriByCandidates($logosDir, ['logo-siutitsm.jpg']),
    ];
}

function resolveUserPhotoDataUri(AppConfig $appConfig, ?string $photoPath): ?string
{
    $relativePath = DocumentHelper::normalizeUploadPath($photoPath);

    if ($relativePath === '') {
        return null;
    }

    $publicPath = resolveSafePathFromRoot($appConfig->upload->publicDir, $relativePath);

    if ($publicPath !== null) {
        return resolveImageDataUriFromFile($publicPath);
    }

    $privatePath = resolveSafePathFromRoot($appConfig->upload->privateDir, $relativePath);

    if ($privatePath !== null) {
        return resolveImageDataUriFromFile($privatePath);
    }

    return null;
}

/**
 * @return array{name: string, role: string, signatureHash: string}
 */
function resolveLeaderSignatory(UserRepositoryInterface $userRepository): array
{
    $default = [
        'name' => 'Sin lider asignado',
        'role' => 'Secretario General',
        'signatureHash' => 'N/D',
    ];

    foreach ($userRepository->listado(true) as $summary) {
        if ($summary->role !== RoleEnum::Lider) {
            continue;
        }

        $leader = $userRepository->findById($summary->id);

        if ($leader === null) {
            continue;
        }

        $fullName = trim($leader->personalInfo->name . ' ' . $leader->personalInfo->surnames);
        $curp = strtoupper(trim((string) $leader->personalInfo->curp));

        return [
            'name' => $fullName !== '' ? $fullName : 'Lider sin nombre',
            'role' => 'Secretario General',
            'signatureHash' => $curp !== '' ? hash('sha256', $curp) : 'N/D',
        ];
    }

    return $default;
}

/**
 * @param array<int, string> $candidateFiles
 */
function resolveImageDataUriByCandidates(string $dir, array $candidateFiles): ?string
{
    foreach ($candidateFiles as $candidateFile) {
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidateFile;

        if (!is_file($path)) {
            continue;
        }

        $dataUri = resolveImageDataUriFromFile($path);

        if ($dataUri !== null) {
            return $dataUri;
        }
    }

    return null;
}

function resolveImageDataUriFromFile(string $path): ?string
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $data = file_get_contents($path);

    if (!is_string($data) || $data === '') {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) ($finfo->file($path) ?: 'application/octet-stream');

    if (!str_starts_with($mimeType, 'image/')) {
        return null;
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($data);
}

function resolveSafePathFromRoot(string $baseDir, string $relativePath): ?string
{
    $normalizedRelativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    $candidatePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalizedRelativePath;

    $realBaseDir = realpath($baseDir);
    $realCandidatePath = realpath($candidatePath);

    if ($realBaseDir === false || $realCandidatePath === false) {
        return null;
    }

    if (!str_starts_with($realCandidatePath, $realBaseDir . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return is_file($realCandidatePath) && is_readable($realCandidatePath)
        ? $realCandidatePath
        : null;
}

/**
 * @return array{primary: string, secondary: string, text: string, muted: string, border: string, surface: string, background: string, onPrimary: string, onSecondary: string}
 */
function resolveCredentialCardTheme($container): array
{
    $theme = [
        'primary' => '#611232',
        'secondary' => '#a57f2c',
        'text' => '#212529',
        'muted' => '#475569',
        'border' => '#cbd5e1',
        'surface' => '#ffffff',
        'background' => '#f8f9fa',
    ];

    try {
        $colorConfig = $container->get(GetColorUseCase::class)->execute();

        if ($colorConfig !== null) {
            $theme['primary'] = normalizeHexColor($colorConfig->primary, $theme['primary']);
            $theme['secondary'] = normalizeHexColor($colorConfig->secondary, $theme['secondary']);
            $theme['text'] = normalizeHexColor($colorConfig->body, $theme['text']);
            $theme['surface'] = normalizeHexColor($colorConfig->white, $theme['surface']);
            $theme['background'] = normalizeHexColor($colorConfig->bodyBackground, $theme['background']);
            $theme['muted'] = normalizeHexColor($colorConfig->dark, $theme['muted']);
            $theme['border'] = normalizeHexColor($colorConfig->light, $theme['border']);
        }
    } catch (Throwable) {
        // Mantener colores por defecto si la carga dinamica falla.
    }

    $theme['onPrimary'] = resolveAccessibleTextColor($theme['primary']);
    $theme['onSecondary'] = resolveAccessibleTextColor($theme['secondary']);
    $theme['muted'] = resolveMutedTextColor($theme['text'], $theme['surface']);
    $theme['border'] = resolveBorderColor($theme['text'], $theme['surface']);

    return $theme;
}

function normalizeHexColor(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
        ? strtolower($value)
        : strtolower($fallback);
}

function resolveAccessibleTextColor(string $hex): string
{
    [$r, $g, $b] = resolveHexToRgb($hex);
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    return $luminance > 0.6 ? '#0f172a' : '#ffffff';
}

/**
 * @return array{0: int, 1: int, 2: int}
 */
function resolveHexToRgb(string $hex): array
{
    $normalized = ltrim($hex, '#');

    return [
        hexdec(substr($normalized, 0, 2)),
        hexdec(substr($normalized, 2, 2)),
        hexdec(substr($normalized, 4, 2)),
    ];
}

function resolveMutedTextColor(string $textHex, string $surfaceHex): string
{
    [$tr, $tg, $tb] = resolveHexToRgb($textHex);
    [$sr, $sg, $sb] = resolveHexToRgb($surfaceHex);

    $r = (int) round($tr * 0.62 + $sr * 0.38);
    $g = (int) round($tg * 0.62 + $sg * 0.38);
    $b = (int) round($tb * 0.62 + $sb * 0.38);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function resolveBorderColor(string $textHex, string $surfaceHex): string
{
    [$tr, $tg, $tb] = resolveHexToRgb($textHex);
    [$sr, $sg, $sb] = resolveHexToRgb($surfaceHex);

    $r = (int) round($tr * 0.24 + $sr * 0.76);
    $g = (int) round($tg * 0.24 + $sg * 0.76);
    $b = (int) round($tb * 0.24 + $sb * 0.76);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
