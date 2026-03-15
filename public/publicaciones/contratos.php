<?php

use App\Bootstrap;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Publication\Application\UseCase\FindPublicationByIdUseCase;
use App\Modules\Publication\Application\UseCase\FindPublicationsByTypeUseCase;
use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;

require_once __DIR__ . "/../../bootstrap.php";

$container = Bootstrap::buildContainer();

$request = new FormRequest();
$redirector = $container->get(Redirector::class);

$findByType = $container->get(FindPublicationsByTypeUseCase::class);
$findById = $container->get(FindPublicationByIdUseCase::class);

$detailId = $request->integer("detalle");

$renderer = $container->get(RendererInterface::class);

if ($detailId > 0) {
    $publication = $findById->execute($detailId);

    if ($publication === null || $publication->type !== PublicationTypeEnum::Contracts) {
        $redirector->to("/publicaciones/contratos.php", [
            "error" => "El contrato solicitado no existe.",
        ])->send();
    }

    $renderer->render(__DIR__ . "/show.latte", [
        "publicacion" => $publication,
        "error" => null,
    ]);
    return;
}

$contratos = array_map(
    static fn(Publication $publication) => [
        "id" => $publication->id,
        "titulo" => $publication->title,
        "resumen" => $publication->summary !== null && trim($publication->summary) !== ""
            ? $publication->summary
            : trim(strip_tags($publication->content)),
        "fecha_publicacion" => $publication->createdAt,
        "imagen_portada" => $publication->thumbnailUrl,
    ],
    $findByType->execute(PublicationTypeEnum::Contracts),
);

$contratosPorAnio = [];

foreach ($contratos as $contrato) {
    $anio = $contrato["fecha_publicacion"]?->format("Y") ?? "Sin fecha";

    if (!isset($contratosPorAnio[$anio])) {
        $contratosPorAnio[$anio] = [];
    }

    $contratosPorAnio[$anio][] = $contrato;
}

uksort(
    $contratosPorAnio,
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

$renderer->render(__DIR__ . "/contratos.latte", [
    "contratosPorAnio" => $contratosPorAnio,
    "error" => $request->input("error"),
]);
