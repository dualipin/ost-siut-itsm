<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Publication\Application\UseCase\FindPublicationByIdUseCase;
use App\Modules\Publication\Application\UseCase\FindPublicationsByTypeUseCase;
use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Shared\Context\UserProviderInterface;
use App\Shared\Domain\Enum\RoleEnum;

require_once __DIR__ . "/../../../bootstrap.php";

$container = Bootstrap::buildContainer();

$middleware = $container->get(MiddlewareFactory::class);
$runner = $container->get(MiddlewareRunner::class);
$runner->runOrRedirect($middleware->auth());

$request = new FormRequest();
$redirector = $container->get(Redirector::class);
$authUser = $container->get(UserProviderInterface::class)->get();

$canManagePublications =
    $authUser !== null &&
    in_array($authUser->role, [RoleEnum::Admin, RoleEnum::Lider], true);

$findByType = $container->get(FindPublicationsByTypeUseCase::class);
$findById = $container->get(FindPublicationByIdUseCase::class);

$detailId = $request->integer("detalle");

$renderer = $container->get(RendererInterface::class);

if ($detailId > 0) {
    $publication = $findById->execute($detailId);

    if ($publication === null || $publication->type !== PublicationTypeEnum::Library) {
        $redirector->to("/portal/publicaciones/acervos.php", [
            "error" => "El acervo bibliográfico solicitado no existe.",
        ])->send();
    }

    $renderer->render(__DIR__ . "/avisos.show.latte", [
        "publicacion" => $publication,
        "canManagePublications" => $canManagePublications,
        "editUrl" => "/portal/publicaciones/editar.php?id={$publication->id}",
        "deleteUrl" => "/portal/publicaciones/eliminar.php",
        "backUrl" => "/portal/publicaciones/acervos.php",
        "success" => $request->input("success"),
        "error" => $request->input("error"),
    ]);
    return;
}

$acervos = array_map(
    static fn(Publication $publication) => [
        "id" => $publication->id,
        "titulo" => $publication->title,
        "resumen" => $publication->summary !== null && trim($publication->summary) !== ""
            ? $publication->summary
            : trim(strip_tags($publication->content)),
        "fecha_publicacion" => $publication->createdAt,
        "imagen_portada" => $publication->thumbnailUrl,
    ],
    $findByType->execute(PublicationTypeEnum::Library),
);

$acervosPorAnio = [];

foreach ($acervos as $acervo) {
    $anio = $acervo["fecha_publicacion"]?->format("Y") ?? "Sin fecha";

    if (!isset($acervosPorAnio[$anio])) {
        $acervosPorAnio[$anio] = [];
    }

    $acervosPorAnio[$anio][] = $acervo;
}

uksort(
    $acervosPorAnio,
    static function (string $a, string $b): int {
        if ($a === "Sin fecha") {
            return 1;
        }

        if ($b === "Sin fecha") {
            return -1;
        }

        return (int) $b <=> (int) $a;
    },
);

$renderer->render(__DIR__ . "/acervos.latte", [
    "acervosPorAnio" => $acervosPorAnio,
    "error" => $request->input("error"),
    "success" => $request->input("success"),
]);
