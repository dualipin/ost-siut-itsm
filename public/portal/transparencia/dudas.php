<?php

use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

# obtencion de los datos
$pdo = \App\Configuracion\MysqlConexion::conexion();
$res = $pdo->query('SELECT  id, nombre, duda, correo, fecha FROM dudas_transparencia ORDER BY fecha DESC');
$dudas = $res->fetchAll(PDO::FETCH_ASSOC);

$datos = [
        'dudas' => $dudas,
];

ServicioLatte::renderizar(__DIR__ . '/dudas.latte', $datos);