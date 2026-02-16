<?php

use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';


$pdo = App\Configuracion\MysqlConexion::conexion();
$participantes_q = $pdo->query("SELECT * FROM participantes ORDER BY id DESC");
$participantes = $participantes_q->fetchAll(PDO::FETCH_ASSOC);

$datos = [
        'participantes' => $participantes
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);