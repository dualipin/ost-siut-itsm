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
        private string $basePath
    ) {
    }

    public function store(string $sourcePath, string $filename): string
    {
        if (!is_dir($this->basePath)) {
            if (!mkdir($this->basePath, 0755, true) && !is_dir($this->basePath)) {
                throw new RuntimeException("No se pudo crear el directorio de destino: {$this->basePath}");
            }
        }
        
        // Anti-collision naming
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $safeFilename = uniqid('transparency_', true) . '.' . $extension;
        
        $destinationPath = $this->basePath . DIRECTORY_SEPARATOR . $safeFilename;
        
        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("No se pudo mover el archivo a: {$destinationPath}");
        }
        
        return $safeFilename;
    }

    public function delete(string $relativePath): void
    {
        $fullPath = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
