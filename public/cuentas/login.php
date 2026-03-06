<?php

use App\Bootstrap;
use App\Http\Exception\TooManyAttemptsException;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Security\CsrfTokenManager;
use App\Infrastructure\Session\PhpSession;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Auth\Service\AuthenticationService;
use App\Shared\Context\UserContext;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();
$redirector = $container->get(Redirector::class);
$sessionManager = $container->get(PhpSession::class);
$csrfManager = $container->get(CsrfTokenManager::class);
$userContext = $container->get(UserContext::class);

$sessionManager->start();

$request = new FormRequest();
$redirect = $request->input("redirect", "/portal/");
$error = $request->input("error");
$email = $request->input("email");

if ($userContext->isAuthenticated()) {
    $redirector->to($redirect)->send();
}

if ($request->isSubmitted()) {
    $submittedToken = $request->input("_csrf_token");
    if (!$submittedToken || !$csrfManager->verify($submittedToken)) {
        $redirector
            ->to($_SERVER["REQUEST_URI"], [
                "email" => $email,
                "error" => "Token de seguridad inválido",
                "redirect" => $redirect,
            ])
            ->send();
    }

    $service = $container->get(AuthenticationService::class);

    try {
        $authenticated = $service->authenticate(
            email: $request->input("email"),
            password: $request->input("password"),
            ipAddress: $_SERVER["REMOTE_ADDR"] ?? null,
            userAgent: $_SERVER["HTTP_USER_AGENT"] ?? null,
        );

        if ($authenticated) {
            $redirector->to($redirect)->send();
        } else {
            $redirector
                ->to($_SERVER["REQUEST_URI"], [
                    "email" => $email,
                    "error" => "Credenciales inválidas",
                    "redirect" => $redirect,
                ])
                ->send();
        }
    } catch (TooManyAttemptsException $e) {
        $redirector
            ->to($_SERVER["REQUEST_URI"], [
                "email" => $email,
                "error" => $e->getMessage(),
                "redirect" => $redirect,
            ])
            ->send();
    }
}

$csrfToken = $csrfManager->generate();

$renderer = $container->get(RendererInterface::class);
$renderer->render("./login.latte", [
    "email" => $email,
    "error" => $error,
    "redirect" => $redirect,
    CsrfTokenManager::getFieldName() => $csrfToken,
]);
