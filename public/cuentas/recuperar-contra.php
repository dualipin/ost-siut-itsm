<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirect;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Auth\Application\UseCase\PasswordResetUseCase;
use App\Modules\Auth\Application\UseCase\RecoverPasswordWithMagicLinkUseCase;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$request = new FormRequest();
$redirect = $container->get(Redirect::class);
$userContext = $container->get(UserContextInterface::class);

if ($userContext->isAuthenticated()) {
    $redirect->to("/portal/")->send();
}

$token = (string) $request->input("token", "");

if ($token !== "") {
    $useCase = $container->get(RecoverPasswordWithMagicLinkUseCase::class);

    $authenticated = $useCase->execute(
        token: $token,
        ipAddress: $_SERVER["REMOTE_ADDR"] ?? null,
        userAgent: $_SERVER["HTTP_USER_AGENT"] ?? null,
    );

    if ($authenticated) {
        $redirect->to("/portal/")->send();
    }

    $redirect
        ->to("/cuentas/recuperar-contra.php", [
            "error" =>
                "El enlace de recuperación no es válido o ya expiró. Solicita uno nuevo.",
        ])
        ->send();
}

if ($request->isSubmitted()) {
    $email = (string) $request->input("email", "");

    if ($email !== "") {
        $useCase = $container->get(PasswordResetUseCase::class);
        $useCase->execute(
            email: $email,
            ipAddress: $_SERVER["REMOTE_ADDR"] ?? null,
            userAgent: $_SERVER["HTTP_USER_AGENT"] ?? null,
        );
    }

    $redirect
        ->to("/cuentas/recuperar-contra.php", [
            "status" =>
                "Si el correo está registrado, enviamos un enlace de recuperación.",
        ])
        ->send();
}

$renderer = $container->get(RendererInterface::class);
$renderer->render("./recuperar-contra.latte", [
    "status" => $request->input("status"),
    "error" => $request->input("error"),
]);
