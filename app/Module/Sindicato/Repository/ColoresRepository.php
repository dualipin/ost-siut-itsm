<?php

namespace App\Module\Sindicato\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Sindicato\Entity\Colores;

class ColoresRepository extends BaseRepository
{
    public function getColores()
    {
        $colores = $this->pdo->query("SELECT * FROM colores_sistema")->fetch();

        if ($colores) {
            return new Colores(
                id: $colores["id"],
                primario: $colores["primario"],
                secundario: $colores["secundario"],
                exito: $colores["exito"],
                info: $colores["info"],
                advertencia: $colores["advertencia"],
                peligro: $colores["peligro"],
                claro: $colores["claro"],
                oscuro: $colores["oscuro"],
                blanco: $colores["blanco"],
                cuerpo: $colores["cuerpo"],
                fondoCuerpo: $colores["fondo-cuerpo"],
            );
        }

        return null;
    }
}
