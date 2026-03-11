<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\Sesion;

require_once __DIR__ . '/../../src/configuracion.php';

$motivo = $_POST['motivo'] ?? null;
$id = $_POST['id'] ?? null;
$estado = $_POST['estado'] ?? 'rechazado';

if ($id === null || $motivo === null || trim($motivo) === '') {
    $r = http_build_query([
            'id' => $id,
            'message' => 'Parámetros inválidos.',
            'status' => 'error'
    ]);

    header("Location: ver.php?$r");
    exit;
}

$pdo = MysqlConexion::conexion();
$actualizar = $pdo->prepare(
        "UPDATE solicitudes_prestamos SET estado = :estado, motivo_rechazo = :motivo, fecha_respuesta = :fecha, fk_aprobador = :aprobador WHERE id = :id"
);
$actualizar->bindParam(':motivo', $motivo);
$actualizar->bindParam(':id', $id);
$fecha = date('Y-m-d H:i:s');
$actualizar->bindParam(':fecha', $fecha);
$aprobador = Sesion::sesionAbierta()->getId();
$actualizar->bindParam(':aprobador', $aprobador);
$actualizar->bindParam(':estado', $estado);
$actualizar->execute();

if ($actualizar->rowCount() > 0) {
    $r = http_build_query([
            'id' => $id,
            'message' => 'Préstamo rechazado.'
    ]);
} else {
    $r = http_build_query([
            'id' => $id,
            'message' => 'No se pudo rechazar el préstamo.',
            'status' => 'error'
    ]);
}
header("Location: ver.php?$r");
exit;