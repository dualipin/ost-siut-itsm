<?php

require_once __DIR__ . '/../../src/configuracion.php';

$id = $_POST['id'] ?? null;
$plazo = $_POST['plazo'] ?? null;
$tasa_interes = $_POST['tasa'] ?? null;
$monto = $_POST['monto'] ?? null;

if ($plazo == null || $tasa_interes == null || $monto == null || $id == null) {
    $r = http_build_query([
            'id' => $id,
            'message' => 'Parámetros inválidos.',
            'status' => 'error'
    ]);

    header("Location: ver.php?$r");
    exit;
}

$pdo = \App\Configuracion\MysqlConexion::conexion();
$actualizar = $pdo->prepare(
        "UPDATE solicitudes_prestamos SET estado = 'aprobado', motivo_rechazo = '', plazo_meses = :plazo, tasa_interes = :tasa, monto_aprobado = :monto WHERE id = :id"
);
$actualizar->bindParam(':plazo', $plazo);
$actualizar->bindParam(':tasa', $tasa_interes);
$actualizar->bindParam(':monto', $monto);
$actualizar->bindParam(':id', $id);
$actualizar->execute();

if ($actualizar->rowCount() > 0) {
    $r = http_build_query([
            'id' => $id,
            'message' => 'Préstamo aprobado. Pendiente de pagaré.'
    ]);
} else {
    $r = http_build_query([
            'id' => $id,
            'message' => 'No se pudo aprobar el préstamo.',
            'status' => 'error'
    ]);
}
header("Location: ver.php?$r");
exit;
