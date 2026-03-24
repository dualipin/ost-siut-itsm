<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\CashBoxes\Application\UseCase\RecordTransactionUseCase;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;
use App\Shared\Utils\UrlBuilder;

require_once __DIR__ . "/../../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth([RoleEnum::Admin, RoleEnum::Finanzas, RoleEnum::Lider]));

$urlBuilder = $container->get(UrlBuilder::class);

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
$type = $_POST['type'] ?? '';
$amount = (float)($_POST['amount'] ?? 0.0);
$referenceNumber = empty($_POST['reference_number']) ? null : $_POST['reference_number'];
$description = empty($_POST['description']) ? null : $_POST['description'];

$attachmentPath = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../../../public/uploads/cajas/movimientos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['attachment']['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    
    if (in_array($mimeType, $allowedMimes)) {
        $extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('tx_') . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
            $attachmentPath = '/uploads/cajas/movimientos/' . $filename;
        }
    } else {
        header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'error' => 'Tipo de archivo adjunto no permitido. Solo se permiten imágenes (JPEG, PNG, WEBP) o PDF.']));
        exit;
    }
}

try {
    $useCase = $container->get(RecordTransactionUseCase::class);
    $useCase->execute($boxId, $categoryId, $user->id, $type, $amount, $referenceNumber, $description, $attachmentPath);
    
    // Redirect back to either the details of the box or movimientos depending on where it came from... 
    // Here we'll just redirect to movimientos since it's the safest single view for transactions.
    header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'success' => 1]));
} catch (Exception $e) {
    header("Location: " . $urlBuilder->to('/portal/cajas/movimientos.php', ['box_id' => $boxId, 'error' => $e->getMessage()]));
}
exit;
