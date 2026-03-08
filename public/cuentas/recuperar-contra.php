<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirect;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Auth\Application\UseCase\PasswordResetUseCase;
use App\Modules\Auth\Application\UseCase\ChangePasswordWithTokenUseCase;
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

// Si hay token, manejamos el flujo de cambio de contraseña
if ($token !== "") {
    $useCase = $container->get(ChangePasswordWithTokenUseCase::class);

    // Validar el token sin procesarlo aún
    $email = $useCase->validateToken($token);

    if (!$email) {
        $redirect
            ->to("/cuentas/recuperar-contra.php", [
                "error" =>
                    "El enlace de recuperación no es válido o ya expiró. Solicita uno nuevo.",
            ])
            ->send();
    }

    // Si el formulario fue enviado con nueva contraseña
    if ($request->isSubmitted()) {
        $password = (string) $request->input("password", "");
        $confirmPassword = (string) $request->input("confirm_password", "");

        if ($password === "" || $confirmPassword === "") {
            $redirect
                ->to("/cuentas/recuperar-contra.php", [
                    "token" => $token,
                    "error" => "Ambos campos de contraseña son requeridos.",
                ])
                ->send();
        }

        if ($password !== $confirmPassword) {
            $redirect
                ->to("/cuentas/recuperar-contra.php", [
                    "token" => $token,
                    "error" => "Las contraseñas no coinciden.",
                ])
                ->send();
        }

        if (strlen($password) < 8) {
            $redirect
                ->to("/cuentas/recuperar-contra.php", [
                    "token" => $token,
                    "error" =>
                        "La contraseña debe tener al menos 8 caracteres.",
                ])
                ->send();
        }

        // Cambiar la contraseña
        $success = $useCase->execute(
            token: $token,
            newPassword: $password,
            ipAddress: $_SERVER["REMOTE_ADDR"] ?? null,
            userAgent: $_SERVER["HTTP_USER_AGENT"] ?? null,
        );

        if ($success) {
            $redirect->to("/portal/")->send();
        }

        $redirect
            ->to("/cuentas/recuperar-contra.php", [
                "error" =>
                    "No se pudo cambiar la contraseña. Intenta de nuevo.",
            ])
            ->send();
    }

    // Mostrar formulario de cambio de contraseña
    $renderer = $container->get(RendererInterface::class);
    $renderer->render("./recuperar-contra.latte", [
        "token" => $token,
        "email" => $email,
        "showPasswordForm" => true,
        "error" => $request->input("error"),
    ]);
    exit();
}

// Flujo normal: solicitud de recuperación
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
    "showPasswordForm" => false,
]);
