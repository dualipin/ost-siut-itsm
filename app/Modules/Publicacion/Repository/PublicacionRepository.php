<?php

namespace App\Module\Publicacion\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Publicacion\Entity\Publicacion;

use function array_map;

final class PublicacionRepository extends BaseRepository
{
    /**
     * @return Publicacion[]
     */
    public function obtenerNoticias(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM publicaciones WHERE tipo = 'noticia' ORDER BY fecha_publicacion DESC",
        );
        $result = $stmt->fetchAll();

        return array_map(
            fn($item) => new Publicacion(
                id: $item["id"],
                titulo: $item["titulo"],
                resumen: $item["resumen"],
                contenido: $item["contenido"],
                fechaPublicacion: $item["fecha_publicacion"],
            ),
            $result,
        );
    }
}
