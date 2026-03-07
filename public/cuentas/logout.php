<?php

use App\Bootstrap;
use App\Http\Response\Redirect;
use App\Modules\Auth\Application\UseCase\LogoutUseCase;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$useCase = $container->get(LogoutUseCase::class);
$redirect = $container->get(Redirect::class);

$useCase->execute(
    ipAddress: $_SERVER["REMOTE_ADDR"] ?? null,
    userAgent: $_SERVER["HTTP_USER_AGENT"] ?? null,
);

$redirect->to("/cuentas/login.php")->send();
