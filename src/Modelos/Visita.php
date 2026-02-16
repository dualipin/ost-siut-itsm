<?php

namespace App\Modelos;

use App\Configuracion\MysqlConexion;
use PDO;
use PDOException;

final class Visita
{
    public function __construct(
        private PDO $pdo
    ) {}


    public function registrarVisita(): void
    {
        $pagina = $_SERVER['REQUEST_URI'] ?? 'desconocida';


        $sql = "";
    }

    public  function agregarVisita(): void
    {
        $insert = "INSERT INTO visitas_pagina (fecha) VALUES (NOW())";
        try {
            $con = $this->pdo;
            $stmt = $con->prepare($insert);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al agregar visita: " . $e->getMessage());
        }
    }

    public function obtenerVisitasHoy(): int
    {
        $con = $this->pdo;
        $sql = "SELECT COUNT(*) AS total FROM visitas_pagina WHERE DATE(fecha) = CURDATE()";
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $resultado['total'];
    }

    public  function obtenerVisitasSemana(): int
    {
        $con = $this->pdo;
        $sql = "SELECT COUNT(*) AS total FROM visitas_pagina WHERE YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)";
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $resultado['total'];
    }
}
