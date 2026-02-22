<?php
use App\Bootstrap;
use App\Http\Response\Redirector;
use App\Infrastructure\Session\SessionManager;
use App\Module\Auth\DTO\AuthLogDTO;
use App\Module\Auth\Enum\AuthLogActionEnum;
use App\Module\Auth\Repository\AuthenticationRepository;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();
$redirector = $container->get(Redirector::class);
$sessionManager = $container->get(SessionManager::class);
$authRepo = $container->get(AuthenticationRepository::class);

// Iniciar sesión si no está iniciada
$sessionManager->start();

// Obtener datos del usuario antes de destruir la sesión
$userId = $sessionManager->get("user_id");
$userEmail = $sessionManager->get("user_email");

// Registrar logout en los logs de autenticación
if ($userId && $userEmail) {
    $authRepo->saveAuthLog(
        new AuthLogDTO(
            action: AuthLogActionEnum::Logout,
            success: true,
            usuarioId: (int) $userId,
            email: $userEmail,
            ipAddress: $_SERVER["REMOTE_ADDR"] ?? "unknown",
            userAgent: $_SERVER["HTTP_USER_AGENT"] ?? "unknown",
        ),
    );
}

// Destruir la sesión
$sessionManager->destroy();

// Redirigir al login
$redirector->to("/cuentas/login.php")->send();