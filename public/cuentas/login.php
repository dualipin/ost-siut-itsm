<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Templating\RendererInterface;
use App\Module\Auth\Service\AuthenticationService;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();
$redirector = $container->get(Redirector::class);

$request = new FormRequest();

$redirect = $request->input("redirect", "/portal/");

if ($request->isSubmitted()) {
    $service = $container->get(AuthenticationService::class);

    $path = $_SERVER["REQUEST_URI"];
    $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";
    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    $email = $request->input("email");
    $password = $request->input("password");

    if ($service->authenticate($email, $password, $ipAddress, $userAgent)) {
        $redirector->to($redirect)->send();
    } else {
        $redirector
            ->to($path, [
                "email" => $email,
                "error" => "Credenciales inválidas",
                "redirect" => $redirect,
            ])
            ->send();
    }
} else {
    $renderer = $container->get(RendererInterface::class);

    $data = [
        "email" => $request->input("email"),
        "error" => $request->input("error"),
        "redirect" => $redirect,
    ];

    $renderer->render("./login.latte", $data);
}
