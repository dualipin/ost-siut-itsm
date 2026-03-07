<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirect;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Auth\Application\UseCase\LoginUseCase;
use App\Modules\Auth\Domain\Exception\TooManyAttemptsException;
use App\Shared\Context\UserContextInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$redirector = $container->get(Redirect::class);
$userContext = $container->get(UserContextInterface::class);

$request = new FormRequest();
$redirect = $request->input("redirect", "/portal/");
$error = $request->input("error");
$email = $request->input("email");

if ($userContext->isAuthenticated()) {
    $redirector->to($redirect)->send();
}

if ($request->isSubmitted()) {
    $useCase = $container->get(LoginUseCase::class);

    try {
        $authenticated = $useCase->execute(
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
            ->to(
                $_SERVER["REQUEST_URI"],
                [
                    "email" => $email,
                    "error" => $e->getMessage(),
                    "redirect" => $redirect,
                ],
                false,
            )
            ->send();
    }
}

$renderer = $container->get(RendererInterface::class);
$renderer->render("./login.latte", [
    "email" => $email,
    "error" => $error,
    "redirect" => $redirect,
]);
