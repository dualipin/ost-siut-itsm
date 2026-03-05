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
            "insert into colores_sistema (
            id,
            primario,
            secundario,
            exito,
            info,
            advertencia,
            peligro,
            claro,
            oscuro,
            blanco,
            cuerpo,
            fondo_cuerpo
        ) values (
            1,
            :primario,
            :secundario,
            :exito,
            :info,
            :advertencia,
            :peligro,
            :claro,
            :oscuro,
            :blanco,
            :cuerpo,
            :fondo_cuerpo
        )
        on duplicate key update
            primario = values(primario),
            secundario = values(secundario),
            exito = values(exito),
            info = values(info),
            advertencia = values(advertencia),
            peligro = values(peligro),
            claro = values(claro),
            oscuro = values(oscuro),
            blanco = values(blanco),
            cuerpo = values(cuerpo),
            fondo_cuerpo = values(fondo_cuerpo)",
        );

        $result = $stmt->execute([
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

        if (!$result) {
            throw new RuntimeException(
                "Error al actualizar colores: " .
                    implode(", ", $stmt->errorInfo()),
            );
        }
    }
}
