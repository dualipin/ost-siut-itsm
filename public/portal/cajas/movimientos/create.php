<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\CashBoxes\Application\UseCase\RecordTransactionUseCase;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$urlBuilder = $container->get(UrlBuilder::class);

$runner->runOrRedirect($middleware->auth());

$authUser = $container->get(UserProviderInterface::class)->get();
if ($authUser === null || !in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Finanzas, RoleEnum::Lider], true)) {
    header('Location: ' . $urlBuilder->to('/portal/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $urlBuilder->to('/portal/cajas/listado.php'));
    exit;
}

$userContext = $container->get(UserContextInterface::class);
$user = $userContext->get();
if (!$user) {
    header("Location: " . $urlBuilder->to('/portal/auth/login.php'));
    exit;
}

$boxId = (int)($_POST['box_id'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);
$contributorUserId = isset($_POST['contributor_user_id']) && $_POST['contributor_user_id'] !== ''
    ? (int) $_POST['contributor_user_id']
    : null;
$type = $_POST['type'] ?? '';
$contributorUserId = $type === 'income' ? $contributorUserId : null;
$amount = (float)($_POST['amount'] ?? 0.0);
$description = empty($_POST['description']) ? null : $_POST['description'];

$attachmentPaths = [];
if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['error'])) {
    $uploadDir = __DIR__ . '/../../../../public/uploads/cajas/movimientos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($_FILES['attachments']['error'] as $index => $errorCode) {
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'error' => 'No se pudo subir uno de los archivos adjuntos.']));
            exit;
        }

        $tmpName = $_FILES['attachments']['tmp_name'][$index] ?? null;
        $originalName = $_FILES['attachments']['name'][$index] ?? '';

        if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }

        $mimeType = $finfo->file($tmpName);
        if (!in_array($mimeType, $allowedMimes, true)) {
            header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'error' => 'Tipo de archivo adjunto no permitido. Solo se permiten imágenes (JPEG, PNG, WEBP) o PDF.']));
            exit;
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = $mimeType === 'application/pdf' ? 'pdf' : 'jpg';
        }

        $filename = uniqid('tx_', true) . '_' . $index . '.' . $extension;
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'error' => 'No se pudo guardar uno de los archivos adjuntos.']));
            exit;
        }

        $attachmentPaths[] = '/uploads/cajas/movimientos/' . $filename;
    }
}

try {
    $useCase = $container->get(RecordTransactionUseCase::class);
    $useCase->execute($boxId, $categoryId, $user->id, $type, $amount, $description, $contributorUserId, $attachmentPaths);
    
    // Redirect back to either the details of the box or movimientos depending on where it came from... 
    // Here we'll just redirect to movimientos since it's the safest single view for transactions.
    header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'success' => 1]));
} catch (Exception $e) {
    header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'error' => $e->getMessage()]));
}
exit;
