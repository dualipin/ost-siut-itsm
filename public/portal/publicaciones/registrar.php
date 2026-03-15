<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Publication\Application\UseCase\CreatePublicationUseCase;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Exception\PublicationAttachmentUploadException;
use App\Modules\Publication\Domain\Exception\PublicationValidationException;
use App\Shared\Context\UserProviderInterface;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$request = new FormRequest();
$redirector = $container->get(Redirector::class);
$userProvider = $container->get(UserProviderInterface::class);
$authUser = $userProvider->get();

if ($authUser === null) {
	$redirector->to("/cuentas/login.php", [
		"redirect" => "/portal/publicaciones/registrar.php",
	])->send();
}

$error = null;
$success = $request->input("success");

$old = [
	"title" => $request->input("title", ""),
	"summary" => $request->input("summary", ""),
	"type" => $request->input("type", PublicationTypeEnum::Alert->value),
	"content" => $request->input("content", ""),
	"expiration_date" => $request->input("expiration_date", ""),
];

if ($request->isSubmitted()) {
	$publicationType = PublicationTypeEnum::tryFrom((string) $old["type"]);

	if ($publicationType === null) {
		$error = "Selecciona un tipo de publicación válido.";
	} else {
		$expirationDate = null;

		if ($old["expiration_date"] !== "") {
			try {
				$expirationDate = new DateTimeImmutable((string) $old["expiration_date"]);
			} catch (Throwable) {
				$error = "La fecha de expiración no es válida.";
			}
		}

		if ($error === null) {
			$createPublication = $container->get(CreatePublicationUseCase::class);

			try {
				$createPublication->execute(
					authorId: $authUser->id,
					title: (string) $old["title"],
					content: (string) $old["content"],
					type: $publicationType,
					summary: trim((string) $old["summary"]),
					expirationDate: $expirationDate,
					uploadedFiles: $request->file("attachments"),
					thumbnailFile: $request->file("thumbnail"),
				);

				$redirector->to("/portal/publicaciones/registrar.php", [
					"success" => "Publicación registrada correctamente.",
				])->send();
			} catch (PublicationValidationException | PublicationAttachmentUploadException $exception) {
				$error = $exception->getMessage();
			} catch (Throwable) {
				$error = "No fue posible registrar la publicación.";
			}
		}
	}
}

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . "/registrar.latte", [
	"error" => $error,
	"success" => $success,
	"old" => $old,
	"types" => PublicationTypeEnum::cases(),
]);
