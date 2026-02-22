<?php

use App\Bootstrap;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Templating\RendererInterface;
use App\Shared\Utils\AuthHelper;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

// Iniciar sesión
$sessionManager = $container->get(SessionManager::class);
$sessionManager->start();


// $middleware = $container->get(Mid)

// Obtener usuario autenticado
$authHelper = $container->get(AuthHelper::class);
$usuario = $authHelper->getAuthenticatedUser();

// Si no está autenticado, redirigir a login
if (!$usuario) {
    header("Location: /cuentas/login.php?redirect=/portal/");
    exit;
}

$renderer = $container->get(RendererInterface::class);
$renderer->render("./index.latte", [
    "usuario" => $usuario,
]);
