<?php

namespace App\Module\Sindicato\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Sindicato\Entity\Colores;
use RuntimeException;

class ColoresRepository extends BaseRepository
{
    public function getColores(): ?Colores
    {
        $colores = $this->pdo
            ->query("SELECT * FROM colores_sistema LIMIT 1")
            ->fetch();

        if ($colores) {
            return new Colores(
                primario: $colores["primario"],
                secundario: $colores["secundario"],
                exito: $colores["exito"],
                info: $colores["info"],
                advertencia: $colores["advertencia"],
                peligro: $colores["peligro"],
                claro: $colores["claro"],
                oscuro: $colores["oscuro"],
                blanco: $colores["blanco"] ?? "#ffffff",
                cuerpo: $colores["cuerpo"] ?? "#212529",
                fondoCuerpo: $colores["fondo_cuerpo"] ?? "#f8f9fa",
            );
        }

        return null;
    }

    public function actualizarColores(Colores $colores): void
    {
        $stmt = $this->pdo->prepare(
            "update colores_sistema set
      primario = :primario,
      secundario = :secundario,
      exito = :exito,
      info = :info,
      advertencia = :advertencia,
      peligro = :peligro,
      claro = :claro,
      oscuro = :oscuro,
      blanco = :blanco,
      cuerpo = :cuerpo,
      fondo_cuerpo = :fondo_cuerpo
      where id = 1",
        );
        $stmt->execute([
            ":primario" => $colores->primario,
            ":secundario" => $colores->secundario,
            ":exito" => $colores->exito,
            ":info" => $colores->info,
            ":advertencia" => $colores->advertencia,
            ":peligro" => $colores->peligro,
            ":claro" => $colores->claro,
            ":oscuro" => $colores->oscuro,
            ":blanco" => $colores->blanco,
            ":cuerpo" => $colores->cuerpo,
            ":fondo_cuerpo" => $colores->fondoCuerpo,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                "No se encontró la configuración de colores.",
            );
        }
    }
}
