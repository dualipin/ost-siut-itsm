<?php

namespace App\Module\Mensajeria\Repository;

use App\Infrastructure\Persistence\BaseRepository;
use App\Module\Mensajeria\DTO\CrearMensajeExternoDTO;

final class MensajeRepository extends BaseRepository
{
    public function registrarMensajeExterno(
        CrearMensajeExternoDTO $mensaje,
    ): void {
        $this->pdo->beginTransaction();

        $stmtMensaje = $this->pdo->prepare(
            "insert into mensajes (tipo, asunto, nombre_externo, correo_externo, telefono_externo, prioridad)
                values (:tipo, :asunto, :nombre_externo, :correo_externo, :telefono_externo, :prioridad)",
        );

        $stmtMensaje->execute([
            "tipo" => $mensaje->tipo->value,
            "asunto" => $mensaje->asunto,
            "nombre_externo" => $mensaje->nombreCompleto,
            "correo_externo" => $mensaje->correo,
            "telefono_externo" => $mensaje->telefono,
            "prioridad" => $mensaje->prioridad->value,
        ]);

        $idMensaje = $this->pdo->lastInsertId();

        $stmtDetalle = $this->pdo->prepare(
            "insert into mensaje_detalles (mensaje_id, mensaje)
                    values (:mensaje_id, :mensaje)",
        );

        $stmtDetalle->execute([
            "mensaje_id" => $idMensaje,
            "mensaje" => $mensaje->mensaje,
        ]);

        $this->pdo->commit();
    }
}
