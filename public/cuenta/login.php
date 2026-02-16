<?php

use App\Bootstrap;
use App\Infrastructure\Templating\RendererInterface;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, "password", FILTER_UNSAFE_RAW);

    echo $email;
} else {
    $renderer = $container->get(RendererInterface::class);

    $data = [
        "email" => $_GET["email"] ?? null,
        "error" => $_GET["error"] ?? null,
        "redirect" => $_GET["redirect"] ?? null,
    ];

    $renderer->render("./login.latte", $data);
}
