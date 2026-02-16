<?php

declare(strict_types=1);


use App\Manejadores\Sesion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;


require_once __DIR__ . '/../../src/configuracion.php';

$mensajeError = null;

SesionProtegida::proteger();

$idMiembro = Sesion::sesionAbierta()->getId();

$pdo = \App\Configuracion\MysqlConexion::conexion();
$prestamos = $pdo->prepare("SELECT * FROM solicitudes_prestamos where fk_miembro = :id_miembro");
$prestamos->bindParam(":id_miembro", $idMiembro);
$prestamos->execute();
$prestamos = $prestamos->fetchAll(PDO::FETCH_ASSOC);

if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    echo json_encode($prestamos);
    exit;
}


$datos = [
        'solicitudes' => $prestamos,
        'mensajeError' => $mensajeError,
];

ServicioLatte::renderizar(__DIR__ . '/index.latte', $datos);
