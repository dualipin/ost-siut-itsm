<?php

namespace App\Modelos;

use App\Configuracion\MysqlConexion;
use PDOException;

final class Visita
{
    public static function agregarVisita(): void
    {
        $insert = "INSERT INTO visitas_pagina (fecha) VALUES (NOW())";
        try {
            $con = MysqlConexion::conexion();
            $stmt = $con->prepare($insert);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al agregar visita: " . $e->getMessage());
        }
    }

    public static function obtenerVisitasHoy(): int
    {
        $con = MysqlConexion::conexion();
        $sql = "SELECT COUNT(*) AS total FROM visitas_pagina WHERE DATE(fecha) = CURDATE()";
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $resultado['total'];
    }

    public static function obtenerVisitasSemana(): int
    {
        $con = MysqlConexion::conexion();
        $sql = "SELECT COUNT(*) AS total FROM visitas_pagina WHERE YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)";
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $resultado['total'];
    }
}