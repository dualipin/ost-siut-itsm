<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Infrastructure\Upload;

use App\Infrastructure\Config\AppConfig;
use App\Modules\Messaging\Domain\Entity\MessageAttachment;
use App\Modules\Messaging\Domain\Exception\MessageAttachmentUploadException;

use function basename;
use function bin2hex;
use function dirname;
use function extension_loaded;
use function file_exists;
use function in_array;
use function is_dir;
use function is_string;
use function ltrim;
use function mkdir;
use function move_uploaded_file;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

final readonly class MessageAttachmentUploader
{
    private const int MaxFileSize = 10 * 1024 * 1024; // 10MB

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

    public function __construct(private AppConfig $appConfig) {}

    public function upload(
        string $tmpPath,
        string $originalName,
        int $size,
    ): MessageAttachment {
        if ($size <= 0 || $size > self::MaxFileSize) {
            throw MessageAttachmentUploadException::uploadFailed("El archivo debe pesar entre 1 byte y 10MB.");
        }

        if ($tmpPath === "" || !file_exists($tmpPath)) {
            throw MessageAttachmentUploadException::uploadFailed("No se encontró el archivo temporal.");
        }

        $mimeType = $this->detectMimeType($tmpPath);

        if (!in_array($mimeType, self::AllowedMimeTypes, true)) {
            throw MessageAttachmentUploadException::uploadFailed("El formato del archivo no está permitido.");
        }

        $safeName = $this->cleanOriginalName($originalName);
        $relativePath = $this->buildRelativePath($safeName, "messaging/attachments");
        $fullPath = $this->appConfig->upload->publicDir . DIRECTORY_SEPARATOR . $relativePath;

        $destinationDir = dirname($fullPath);

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            throw MessageAttachmentUploadException::uploadFailed("No se pudo crear el directorio de destino.");
        }

        if (!move_uploaded_file($tmpPath, $fullPath)) {
            throw MessageAttachmentUploadException::uploadFailed("No se pudo mover el archivo cargado.");
        }

        return new MessageAttachment(
            id: null,
            messageId: 0, // A ser llenado por el repositorio o caso de uso
            filePath: $relativePath,
            fileName: $safeName,
            mimeType: $mimeType,
            fileSize: $size,
        );
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
            throw MessageAttachmentUploadException::uploadFailed("La extensión fileinfo es requerida.");
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);

        if (!is_string($mimeType) || $mimeType === "") {
            throw MessageAttachmentUploadException::uploadFailed("No se pudo detectar el tipo MIME.");
        }

        return strtolower($mimeType);
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
