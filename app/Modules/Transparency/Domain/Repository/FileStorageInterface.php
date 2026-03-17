<?php

declare(strict_types=1);

namespace App\Modules\Transparency\Domain\Repository;

interface FileStorageInterface
{
    /**
     * @param string $sourcePath Ruta temporal del archivo
     * @param string $filename Nombre original del archivo
     * @param bool $isPrivate Si el archivo debe ir a una ruta privada
     * @return string Ruta relativa donde fue guardado (para BD)
     */
    public function store(string $sourcePath, string $filename, bool $isPrivate = false): string;

    /**
     * @param string $relativePath Ruta de BD a eliminar
     * @param bool $isPrivate Si el archivo a eliminar está en una ruta privada
     */
    public function delete(string $relativePath, bool $isPrivate = false): void;
}
