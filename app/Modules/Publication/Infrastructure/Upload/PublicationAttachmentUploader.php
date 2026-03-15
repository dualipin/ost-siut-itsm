<?php

namespace App\Modules\Publication\Infrastructure\Upload;

use App\Infrastructure\Config\AppConfig;
use App\Modules\Publication\Domain\Entity\PublicationAttachment;
use App\Modules\Publication\Domain\Enum\PublicationAttachmentTypeEnum;
use App\Modules\Publication\Domain\Exception\PublicationAttachmentUploadException;
use function basename;
use function extension_loaded;
use function file_exists;
use function is_dir;
use function mkdir;
use function move_uploaded_file;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function sprintf;
use function strtolower;
use function trim;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

final readonly class PublicationAttachmentUploader
{
    private const int MaxFileSize = 10 * 1024 * 1024;
    private const int MaxThumbnailSize = 5 * 1024 * 1024;

    /**
     * @var array<string>
     */
    private const array AllowedMimeTypes = [
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp",
        "application/pdf",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/vnd.ms-excel",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/vnd.ms-powerpoint",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "text/plain",
        "text/csv",
        "application/zip",
        "application/x-zip-compressed",
    ];

    /**
     * @var array<string>
     */
    private const array AllowedThumbnailMimeTypes = [
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp",
    ];

    public function __construct(private AppConfig $appConfig) {}

    public function upload(
        string $tmpPath,
        string $originalName,
        int $size,
    ): PublicationAttachment {
        $file = $this->storeFile(
            tmpPath: $tmpPath,
            originalName: $originalName,
            size: $size,
            maxSize: self::MaxFileSize,
            allowedMimeTypes: self::AllowedMimeTypes,
            folder: "publications/attachments",
            invalidSizeMessage: "Cada adjunto debe pesar entre 1 byte y 10MB.",
            invalidTypeMessage: "Uno de los adjuntos no tiene un formato permitido.",
        );

        $attachmentType = str_starts_with($file["mimeType"], "image/")
            ? PublicationAttachmentTypeEnum::Image
            : PublicationAttachmentTypeEnum::Document;

        return new PublicationAttachment(
            id: null,
            publicationId: 0,
            filePath: $file["relativePath"],
            mimeType: $file["mimeType"],
            type: $attachmentType,
            description: $file["safeName"],
        );
    }

    public function uploadThumbnail(
        string $tmpPath,
        string $originalName,
        int $size,
    ): string {
        $file = $this->storeFile(
            tmpPath: $tmpPath,
            originalName: $originalName,
            size: $size,
            maxSize: self::MaxThumbnailSize,
            allowedMimeTypes: self::AllowedThumbnailMimeTypes,
            folder: "publications/thumbnails",
            invalidSizeMessage: "La miniatura debe pesar entre 1 byte y 5MB.",
            invalidTypeMessage: "La miniatura debe ser una imagen JPG, PNG, GIF o WEBP.",
        );

        return $file["relativePath"];
    }

    public function delete(string $relativePath): void
    {
        $fullPath = $this->appConfig->upload->publicDir . DIRECTORY_SEPARATOR . ltrim($relativePath, "\\/");

        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function detectMimeType(string $tmpPath): string
    {
        if (!extension_loaded("fileinfo")) {
            throw new PublicationAttachmentUploadException(
                "La extensión fileinfo es requerida para validar adjuntos.",
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);

        if (!is_string($mimeType) || $mimeType === "") {
            throw new PublicationAttachmentUploadException(
                "No se pudo detectar el tipo MIME de un adjunto.",
            );
        }

        return strtolower($mimeType);
    }

    /**
     * @param array<string> $allowedMimeTypes
     * @return array{relativePath: string, mimeType: string, safeName: string}
     */
    private function storeFile(
        string $tmpPath,
        string $originalName,
        int $size,
        int $maxSize,
        array $allowedMimeTypes,
        string $folder,
        string $invalidSizeMessage,
        string $invalidTypeMessage,
    ): array {
        if ($size <= 0 || $size > $maxSize) {
            throw new PublicationAttachmentUploadException($invalidSizeMessage);
        }

        if ($tmpPath === "" || !file_exists($tmpPath)) {
            throw new PublicationAttachmentUploadException(
                "No se encontró el archivo temporal para la carga.",
            );
        }

        $mimeType = $this->detectMimeType($tmpPath);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new PublicationAttachmentUploadException($invalidTypeMessage);
        }

        $safeName = $this->cleanOriginalName($originalName);
        $relativePath = $this->buildRelativePath($safeName, $folder);
        $fullPath = $this->appConfig->upload->publicDir . DIRECTORY_SEPARATOR . $relativePath;

        $destinationDir = dirname($fullPath);

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            throw new PublicationAttachmentUploadException(
                "No fue posible crear el directorio para almacenar archivos.",
            );
        }

        if (!move_uploaded_file($tmpPath, $fullPath)) {
            throw new PublicationAttachmentUploadException(
                "No fue posible almacenar el archivo cargado.",
            );
        }

        return [
            "relativePath" => $relativePath,
            "mimeType" => $mimeType,
            "safeName" => $safeName,
        ];
    }

    private function buildRelativePath(string $originalName, string $folder): string
    {
        $safeBaseName = $this->cleanOriginalName($originalName);
        $nameWithoutExtension = pathinfo($safeBaseName, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($safeBaseName, PATHINFO_EXTENSION));

        if ($nameWithoutExtension === "") {
            $nameWithoutExtension = "archivo";
        }

        $uniquePrefix = bin2hex(random_bytes(8));
        $fileName = $extension !== ""
            ? sprintf("%s_%s.%s", $nameWithoutExtension, $uniquePrefix, $extension)
            : sprintf("%s_%s", $nameWithoutExtension, $uniquePrefix);

        return rtrim($folder, "\\/") . "/" . $fileName;
    }

    private function cleanOriginalName(string $originalName): string
    {
        $basename = basename(trim($originalName));

        if ($basename === "") {
            return "adjunto";
        }

        $safe = preg_replace('/[^a-zA-Z0-9._-]/', "_", $basename);

        if (!is_string($safe) || $safe === "" || preg_match('/^_+$/', $safe)) {
            return "adjunto";
        }

        return $safe;
    }
}
