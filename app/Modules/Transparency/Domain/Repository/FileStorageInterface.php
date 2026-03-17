<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Repository;

interface FileStorageInterface
{
    /**
     * @param string $sourcePath Ruta temporal del archivo
     * @param string $filename Nombre original del archivo
     * @return string Ruta relativa donde fue guardado (para BD)
     */
    public function store(string $sourcePath, string $filename): string;

    /**
     * @param string $relativePath Ruta de BD a eliminar
     */
    public function delete(string $relativePath): void;
}
