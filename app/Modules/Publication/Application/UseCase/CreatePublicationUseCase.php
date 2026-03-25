<?php

namespace App\Modules\Publication\Application\UseCase;

use App\Infrastructure\Persistence\TransactionManager;
use App\Modules\Publication\Domain\Entity\Publication;
use App\Modules\Publication\Domain\Entity\PublicationAttachment;
use App\Modules\Publication\Domain\Enum\PublicationAttachmentTypeEnum;
use App\Modules\Publication\Domain\Enum\PublicationTypeEnum;
use App\Modules\Publication\Domain\Exception\PublicationAttachmentUploadException;
use App\Modules\Publication\Domain\Exception\PublicationValidationException;
use App\Modules\Publication\Domain\Repository\PublicationRepositoryInterface;
use App\Modules\Publication\Infrastructure\Upload\PublicationAttachmentUploader;
use DateTimeImmutable;
use Throwable;
use function html_entity_decode;
use function is_array;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const ENT_XML1;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

final readonly class CreatePublicationUseCase
{
    public function __construct(
        private PublicationRepositoryInterface $publicationRepository,
        private PublicationAttachmentUploader $attachmentUploader,
        private TransactionManager $transactionManager,
    ) {}

    /**
     * @param array<string, mixed>|null $uploadedFiles
     * @param array<string, mixed>|null $thumbnailFile
     */
    public function execute(
        int $authorId,
        string $title,
        string $content,
        PublicationTypeEnum $type,
        ?string $summary = null,
        ?DateTimeImmutable $expirationDate = null,
        ?array $uploadedFiles = null,
        ?array $thumbnailFile = null,
    ): int {
        $cleanTitle = trim($title);

        if ($cleanTitle === "") {
            throw new PublicationValidationException("El título es obligatorio.");
        }

        if (mb_strlen($cleanTitle) > 100) {
            throw new PublicationValidationException(
                "El título no puede superar los 100 caracteres.",
            );
        }

        $cleanSummary = $summary !== null ? trim($summary) : null;

        if ($cleanSummary !== null && mb_strlen($cleanSummary) > 255) {
            throw new PublicationValidationException(
                "El resumen no puede superar los 255 caracteres.",
            );
        }

        $cleanContent = $this->sanitizeHtml($content);

        if ($this->isBlankHtml($cleanContent)) {
            throw new PublicationValidationException("El contenido es obligatorio.");
        }

        if (
            $expirationDate !== null &&
            $expirationDate->setTime(0, 0) < (new DateTimeImmutable("today"))->setTime(0, 0)
        ) {
            throw new PublicationValidationException(
                "La fecha de expiración no puede estar en el pasado.",
            );
        }

        $uploadedAttachments = $this->normalizeUploadedFiles($uploadedFiles);
        $storedAttachments = [];
        $storedThumbnail = null;

        try {
            $normalizedThumbnail = $this->normalizeSingleUploadedFile($thumbnailFile);

            if ($normalizedThumbnail !== null) {
                $storedThumbnail = $this->attachmentUploader->uploadThumbnail(
                    tmpPath: $normalizedThumbnail["tmp_name"],
                    originalName: $normalizedThumbnail["name"],
                    size: $normalizedThumbnail["size"],
                );
            }

            foreach ($uploadedAttachments as $file) {
                $storedAttachments[] = $this->attachmentUploader->upload(
                    tmpPath: $file["tmp_name"],
                    originalName: $file["name"],
                    size: $file["size"],
                );
            }

            $thumbnailUrl = $storedThumbnail ?? $this->resolveThumbnailUrl($storedAttachments);

            return $this->transactionManager->transactional(function () use (
                $authorId,
                $cleanTitle,
                $cleanSummary,
                $cleanContent,
                $type,
                $storedAttachments,
                $thumbnailUrl,
                $expirationDate,
            ) {
                $publication = new Publication(
                    id: 0,
                    authorId: $authorId,
                    title: $cleanTitle,
                    content: $cleanContent,
                    type: $type,
                    attachments: $storedAttachments,
                    thumbnailUrl: $thumbnailUrl,
                    summary: $cleanSummary !== "" ? $cleanSummary : null,
                    expirationDate: $expirationDate,
                    createdAt: null,
                );

                return $this->publicationRepository->create($publication);
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $attachment) {
                $this->attachmentUploader->delete($attachment->filePath);
            }

            if ($storedThumbnail !== null) {
                $this->attachmentUploader->delete($storedThumbnail);
            }

            if ($exception instanceof PublicationValidationException) {
                throw $exception;
            }

            if ($exception instanceof PublicationAttachmentUploadException) {
                throw $exception;
            }

            throw new PublicationValidationException(
                "No fue posible registrar la publicación.",
                previous: $exception,
            );
        }
    }

    private function sanitizeHtml(string $content): string
    {
        $clean = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', "", $content);

        if (!is_string($clean)) {
            $clean = $content;
        }

        return trim(
            strip_tags(
                $clean,
                "<p><br><strong><em><u><s><a><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6><pre><code><span>",
            ),
        );
    }

    private function isBlankHtml(string $content): bool
    {
        $text = trim(
            html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1),
        );

        return $text === "";
    }

    /**
     * @param PublicationAttachment[] $attachments
     */
    private function resolveThumbnailUrl(array $attachments): ?string
    {
        foreach ($attachments as $attachment) {
            if ($attachment->type === PublicationAttachmentTypeEnum::Image) {
                return $attachment->filePath;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $uploadedFiles
     * @return array<int, array{name: string, tmp_name: string, size: int}>
     */
    private function normalizeUploadedFiles(?array $uploadedFiles): array
    {
        if ($uploadedFiles === null || $uploadedFiles === []) {
            return [];
        }

        $names = $uploadedFiles["name"] ?? null;
        $tmpNames = $uploadedFiles["tmp_name"] ?? null;
        $errors = $uploadedFiles["error"] ?? null;
        $sizes = $uploadedFiles["size"] ?? null;

        if (
            !is_array($names) ||
            !is_array($tmpNames) ||
            !is_array($errors) ||
            !is_array($sizes)
        ) {
            throw new PublicationAttachmentUploadException(
                "El formato de adjuntos recibido no es válido.",
            );
        }

        $result = [];

        foreach ($names as $index => $name) {
            $errorCode = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);

            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new PublicationAttachmentUploadException(
                    $this->uploadErrorMessage($errorCode, true),
                );
            }

            $fileName = trim((string) $name);
            $tmpName = trim((string) ($tmpNames[$index] ?? ""));
            $size = (int) ($sizes[$index] ?? 0);

            if ($fileName === "" || $tmpName === "") {
                throw new PublicationAttachmentUploadException(
                    "Uno de los adjuntos es inválido.",
                );
            }

            $result[] = [
                "name" => $fileName,
                "tmp_name" => $tmpName,
                "size" => $size,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $uploadedFile
     * @return array{name: string, tmp_name: string, size: int}|null
     */
    private function normalizeSingleUploadedFile(?array $uploadedFile): ?array
    {
        if ($uploadedFile === null || $uploadedFile === []) {
            return null;
        }

        $error = (int) ($uploadedFile["error"] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new PublicationAttachmentUploadException(
                $this->uploadErrorMessage($error, false),
            );
        }

        $name = trim((string) ($uploadedFile["name"] ?? ""));
        $tmpName = trim((string) ($uploadedFile["tmp_name"] ?? ""));
        $size = (int) ($uploadedFile["size"] ?? 0);

        if ($name === "" || $tmpName === "") {
            throw new PublicationAttachmentUploadException(
                "El archivo de miniatura es inválido.",
            );
        }

        return [
            "name" => $name,
            "tmp_name" => $tmpName,
            "size" => $size,
        ];
    }

    private function uploadErrorMessage(int $errorCode, bool $isAttachment): string
    {
        $resource = $isAttachment ? "Uno de los adjuntos" : "La miniatura";

        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $resource . " supera el tamaño permitido por el servidor.",
            UPLOAD_ERR_PARTIAL => $resource . " se cargó parcialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "El servidor no tiene configurado un directorio temporal para cargas.",
            UPLOAD_ERR_CANT_WRITE => "El servidor no pudo escribir el archivo temporal en disco.",
            UPLOAD_ERR_EXTENSION => "Una extensión de PHP detuvo la carga del archivo.",
            default => $resource . " no se pudo cargar correctamente (código " . $errorCode . ").",
        };
    }
}
