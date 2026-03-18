<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Infrastructure\Storage;

use App\Modules\Transparency\Domain\Repository\FileStorageInterface;
use RuntimeException;

final readonly class LocalAttachmentStorage implements FileStorageInterface
{
    /**
     * Sigue la regla B. Portabilidad de Recursos (Pattern: Path Injection)
     */
    public function __construct(
        private string $publicBasePath,
        private string $privateBasePath
    ) {
    }

    public function store(string $sourcePath, string $filename, bool $isPrivate = false): string
    {
        $targetBasePath = $isPrivate ? $this->privateBasePath : $this->publicBasePath;
        
        if (!is_dir($targetBasePath)) {
            if (!mkdir($targetBasePath, 0755, true) && !is_dir($targetBasePath)) {
                throw new RuntimeException("No se pudo crear el directorio de destino: {$targetBasePath}");
            }
        }
        
        // Anti-collision naming
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $safeFilename = uniqid('transparency_', true) . '.' . $extension;
        
        $destinationPath = $targetBasePath . DIRECTORY_SEPARATOR . $safeFilename;
        
        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("No se pudo mover el archivo a: {$destinationPath}");
        }
        
        return $safeFilename;
    }

    public function delete(string $relativePath, bool $isPrivate = false): void
    {
        $targetBasePath = $isPrivate ? $this->privateBasePath : $this->publicBasePath;
        $fullPath = rtrim($targetBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
