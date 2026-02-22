<?php

use App\Bootstrap;
use App\Http\Exception\TooManyAttemptsException;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Security\CsrfTokenManager;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Auth\Service\AuthenticationService;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();
$redirector = $container->get(Redirector::class);
$sessionManager = $container->get(SessionManager::class);
$csrfManager = $container->get(CsrfTokenManager::class);

// Iniciar sesión
$sessionManager->start();

$request = new FormRequest();

$redirect = $request->input("redirect", "/portal/");
$error = $request->input("error");
$email = $request->input("email");

if ($request->isSubmitted()) {
    // Verificar CSRF token
    $submittedToken = $request->input("_csrf_token");
    if (!$submittedToken || !$csrfManager->verify($submittedToken)) {
        $error = "Token de seguridad inválido";
        $redirector
            ->to($_SERVER["REQUEST_URI"], [
                "email" => $email,
                "error" => $error,
                "redirect" => $redirect,
            ])
            ->send();
    }

    $service = $container->get(AuthenticationService::class);

    $path = $_SERVER["REQUEST_URI"];
    $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";
    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    $email = $request->input("email");
    $password = $request->input("password");

    try {
        if ($service->authenticate($email, $password, $ipAddress, $userAgent)) {
            // Guardar usuario en sesión
            $sessionManager->set("user_id", $service->getCurrentUser()->id);
            $sessionManager->set("user_email", $service->getCurrentUser()->email);
            $sessionManager->regenerate();
            
            $redirector->to($redirect)->send();
        } else {
            $error = "Credenciales inválidas";
            $redirector
                ->to($path, [
                    "email" => $email,
                    "error" => $error,
                    "redirect" => $redirect,
                ])
                ->send();
        }
    } catch (TooManyAttemptsException $e) {
        $redirector
            ->to($path, [
                "email" => $email,
                "error" => $e->getMessage(),
                "redirect" => $redirect,
            ])
            ->send();
    }
}

// Generar nuevo CSRF token para GET requests y redisplays
$csrfToken = $csrfManager->generate();

$renderer = $container->get(RendererInterface::class);

$data = [
    "email" => $email,
    "error" => $error,
    "redirect" => $redirect,
    CsrfTokenManager::getFieldName() => $csrfToken,
];

$renderer->render("./login.latte", $data);


