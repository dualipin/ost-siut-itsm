<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Modules\Loan\Domain\Repository\PaymentConfigRepositoryInterface;
use App\Shared\Context\UserContextInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . '/../../../../bootstrap.php';

header('Content-Type: application/json');

$container  = Bootstrap::buildContainer();
$middleware = $container->get(MiddlewareFactory::class);
$runner     = $container->get(MiddlewareRunner::class);

// Auth guard — returns 401 instead of redirect for AJAX
$userContext = $container->get(UserContextInterface::class);
$currentUser = $userContext->get();

if ($currentUser === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

if (!in_array($currentUser->role, [RoleEnum::Lider, RoleEnum::Finanzas, RoleEnum::Admin], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$configId     = filter_var($_POST['config_id'] ?? '', FILTER_VALIDATE_INT);
$status       = trim((string) ($_POST['status'] ?? ''));
$observations = trim((string) ($_POST['observations'] ?? '')) ?: null;

$allowedStatuses = ['pendiente', 'validado', 'rechazado'];

if ($configId === false || $configId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID de configuración inválido']);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Estado no permitido']);
    exit;
}

try {
    $repo = $container->get(PaymentConfigRepositoryInterface::class);
    $repo->updateDocumentStatus($configId, $status, $observations);

    echo json_encode(['ok' => true, 'new_status' => $status]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
