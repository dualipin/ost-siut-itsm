<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';
SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$msg = '';
$ok = false;

$con = MysqlConexion::conexion();

$agremiados = $con->query(
        "SELECT id, nombre, apellidos, curp, telefono, departamento  FROM miembros where activo=true order by nombre"
)
        ->fetchAll(PDO::FETCH_ASSOC);


if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
    $msg = 'Agremiado eliminado correctamente.';
    $ok = true;
}

$datos = [
        'agremiados' => $agremiados,
        'msg' => $msg,
        'ok' => $ok
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);