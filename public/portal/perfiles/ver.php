<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Provider\UserContextProvider;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);

$runner->runOrRedirect($middleware->auth());

$userContextProvider = $container->get(UserContextProvider::class);
$user = $userContextProvider->get();

if ($user === null) {
	http_response_code(401);
	exit;
}

$userRepository = $container->get(UserRepositoryInterface::class);
$profileUser = $userRepository->findById($user->id);

$perfil = [
	"id" => $user->id,
	"nombre" => $profileUser?->personalInfo->name ?? $user->name,
	"apellidos" => $profileUser?->personalInfo->surnames ?? "",
	"curp" => $profileUser?->personalInfo->curp ?? "",
	"direccion" => $profileUser?->personalInfo->address ?? "",
	"telefono" => $profileUser?->personalInfo->phone ?? "",
	"correo" => $profileUser?->email ?? $user->email,
	"nss" => $profileUser?->workData->nss ?? "",
	"categoria" => $profileUser?->workData->category,
	"departamento" => $profileUser?->workData->department,
	"rol" => $profileUser?->role->value ?? $user->role->value,
	"fecha_nacimiento" => $profileUser?->personalInfo->birthdate?->format("Y-m-d"),
	"fecha_ingreso" => $profileUser?->workData->workStartDate?->format("Y-m-d"),
];

$docs = [
	"perfil" => $profileUser?->personalInfo->photo ?? "",
	"afiliacion" => "",
	"comprobante_domicilio" => "",
	"ine" => "",
	"comprobante_pago" => "",
];

$appConfig = $container->get(AppConfig::class);
$baseUrl = rtrim($appConfig->baseUrl, "/") . "/";

$renderer = $container->get(RendererInterface::class);

$renderer->render(__DIR__ . "/ver.latte", [
	"user" => $user,
	"perfil" => $perfil,
	"docs" => $docs,
	"baseUrl" => $baseUrl,
]);
