<?php

use App\Bootstrap;
use App\Http\Middleware\MiddlewareFactory;
use App\Http\Middleware\MiddlewareRunner;
use App\Http\Request\FormRequest;
use App\Http\Response\Redirector;
use App\Infrastructure\Templating\RendererInterface;
use App\Modules\Publication\Application\UseCase\FindPublicationByIdUseCase;
use App\Modules\Publication\Application\UseCase\UpdatePublicationUseCase;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Exception\PublicationAttachmentUploadException;
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

$publicationId = $request->integer("id");

if ($publicationId <= 0) {
    $redirector->to("/portal/publicaciones/avisos.php", [
        "error" => "La publicación a editar no es válida.",
    ])->send();
}

$findPublicationById = $container->get(FindPublicationByIdUseCase::class);
$publication = $findPublicationById->execute($publicationId);

if ($publication === null) {
    $redirector->to("/portal/publicaciones/avisos.php", [
        "error" => "La publicación que intentas editar no existe.",
    ])->send();
}

$error = null;

$old = [
    "title" => $request->input("title", $publication->title),
    "summary" => $request->input("summary", $publication->summary ?? ""),
    "type" => $request->input("type", $publication->type->value),
    "content" => $request->input("content", $publication->content),
    "remove_attachment_ids" => normalizeAttachmentIdsInput(
        $request->input("remove_attachment_ids", []),
    ),
    "expiration_date" => $request->input(
        "expiration_date",
        $publication->expirationDate?->format("Y-m-d") ?? "",
    ),
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
            $updatePublication = $container->get(UpdatePublicationUseCase::class);

            try {
                $updatePublication->execute(
                    publicationId: $publicationId,
                    title: (string) $old["title"],
                    content: (string) $old["content"],
                    type: $publicationType,
                    summary: trim((string) $old["summary"]),
                    expirationDate: $expirationDate,
                    uploadedFiles: $request->file("attachments"),
                    thumbnailFile: $request->file("thumbnail"),
                    removeAttachmentIds: $old["remove_attachment_ids"],
                );

                $redirector->to(resolvePublicationListPath($publicationType), [
                    "detalle" => $publicationId,
                    "success" => "Publicación actualizada correctamente.",
                ])->send();
            } catch (PublicationValidationException | PublicationAttachmentUploadException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable) {
                $error = "No fue posible actualizar la publicación.";
            }
        }
    }
}

$renderer = $container->get(RendererInterface::class);
$renderer->render(__DIR__ . "/editar.latte", [
    "error" => $error,
    "old" => $old,
    "publication" => $publication,
    "types" => PublicationTypeEnum::cases(),
    "backUrl" => resolvePublicationListPath($publication->type),
    "removeAttachmentIdsLookup" => buildAttachmentIdLookup(
        $old["remove_attachment_ids"],
    ),
]);

function resolvePublicationListPath(PublicationTypeEnum $type): string
{
    return match ($type) {
        PublicationTypeEnum::Alert => "/portal/publicaciones/avisos.php",
        PublicationTypeEnum::News => "/portal/publicaciones/noticias.php",
        PublicationTypeEnum::Management => "/portal/publicaciones/gestiones.php",
        PublicationTypeEnum::Contracts => "/portal/publicaciones/contratos.php",
    };
}

/**
 * @param mixed $rawValue
 * @return int[]
 */
function normalizeAttachmentIdsInput(mixed $rawValue): array
{
    if ($rawValue === null || $rawValue === "") {
        return [];
    }

    $values = is_array($rawValue) ? $rawValue : [$rawValue];
    $normalized = [];

    foreach ($values as $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $id = (int) $value;

        if ($id <= 0) {
            continue;
        }

        $normalized[$id] = $id;
    }

    return array_values($normalized);
}

/**
 * @param int[] $attachmentIds
 * @return array<int, bool>
 */
function buildAttachmentIdLookup(array $attachmentIds): array
{
    $lookup = [];

    foreach ($attachmentIds as $attachmentId) {
        $lookup[(int) $attachmentId] = true;
    }

    return $lookup;
}
