<?php

namespace App\Module\Prestamo\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Prestamo\Entity\CategoriaTipoIngreso;

use function array_map;

final class PrestamoRepository extends BaseRepository
{
    /**
     * @return CategoriaTipoIngreso[]
     */
    public function obtenerCategoriasTipoIngreso(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM cat_tipos_ingreso");

        return array_map(
            fn($row) => new CategoriaTipoIngreso(
                id: $row["tipo_ingreso_id"],
                nombre: $row["nombre"],
                descripcion: $row["descripcion"],
                esPeriodico: $row["es_periodico"],
                frecuenciaDias: $row["frecuencia_dias"],
                mesPagoTentativo: $row["mes_pago_tentativo"],
                diaPagoTentativo: $row["dia_pago_tentativo"],
                activo: (bool) $row["activo"],
            ),
            $stmt->fetchAll(),
        );
    }
}
