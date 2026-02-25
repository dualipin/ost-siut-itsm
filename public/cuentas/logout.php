<?php

use App\Bootstrap;
use App\Http\Response\Redirector;
use App\Infrastructure\Session\SessionManager;
use App\Module\Auth\Service\AuthenticationService;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();
$sessionManager = $container->get(SessionManager::class);
$sessionManager->start();

$authService = $container->get(AuthenticationService::class);
$redirector = $container->get(Redirector::class);

$authService->logout(
    ipAddress: $_SERVER["REMOTE_ADDR"] ?? null,
    userAgent: $_SERVER["HTTP_USER_AGENT"] ?? null,
);

$redirector->to("/cuentas/login.php")->send();
