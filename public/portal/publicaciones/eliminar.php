<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Modules\Publication\Application\UseCase\DeletePublicationUseCase;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Exception\PublicationValidationException;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$redirector = $container->get(Redirector::class);
$request = new FormRequest();
$authUser = $container->get(UserProviderInterface::class)->get();

$canManagePublications =
    $authUser !== null &&
    in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);

if (!$canManagePublications) {
    $redirector->to("/portal/acceso-denegado.php")->send();
}

if (!$request->is("POST")) {
    $redirector->to("/portal/publicaciones/avisos.php")->send();
}

$publicationId = $request->integer("id");

if ($publicationId <= 0) {
    $redirector->to("/portal/publicaciones/avisos.php", [
        "error" => "La publicación a eliminar no es válida.",
    ])->send();
}

$deletePublication = $container->get(DeletePublicationUseCase::class);

try {
    $publicationType = $deletePublication->execute($publicationId);

    $redirector->to(resolvePublicationListPath($publicationType), [
        "success" => "Publicación eliminada correctamente.",
    ])->send();
} catch (PublicationValidationException $exception) {
    $redirector->to("/portal/publicaciones/avisos.php", [
        "error" => $exception->getMessage(),
    ])->send();
}

function resolvePublicationListPath(PublicationTypeEnum $type): string
{
    return match ($type) {
        PublicationTypeEnum::Alert => "/portal/publicaciones/avisos.php",
        PublicationTypeEnum::News => "/portal/publicaciones/noticias.php",
        PublicationTypeEnum::Management => "/portal/publicaciones/gestiones.php",
        PublicationTypeEnum::Contracts => "/portal/publicaciones/contratos.php",
    };
}
