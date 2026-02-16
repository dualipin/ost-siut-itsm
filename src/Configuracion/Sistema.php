<?php

namespace App\Configuracion;

use PDO;

class Sistema
{
    public function __construct(private readonly \PDO $pdo)
    {
    }


    public function obtenerColores(): array
    {
        $r = $this->pdo->query("SELECT * FROM colores_sistema");
        return $r->fetch();
    }
}