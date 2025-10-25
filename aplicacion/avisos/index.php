<?php

use App\Configuracion\MysqlConexion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';


$conn = MysqlConexion::conexion();

$sql = "SELECT * FROM publicaciones 
WHERE (expiracion IS NULL OR expiracion >= CURDATE())
AND tipo = 'aviso'
ORDER BY fecha DESC;";
$resultado = $conn->query($sql);
$avisos = $resultado->fetchAll(PDO::FETCH_ASSOC);

ServicioLatte::renderizar(__DIR__ . '/index.latte', ['avisos' => $avisos]);