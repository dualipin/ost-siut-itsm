<?php

require_once __DIR__ . '/../../src/configuracion.php';

$prestamo_id = $_POST['prestamo_id'] ?? null;
$pago_id = $_POST['pago_id'] ?? null;

if ($prestamo_id === null || $pago_id === null) {
    $r = http_build_query([
            'id' => $prestamo_id,
            'message' => 'Parámetros inválidos.',
            'status' => 'error'
    ]);

    header("Location: ver.php?$r");
    exit;
}

$pdo = \App\Configuracion\MysqlConexion::conexion();
$marcarPago = $pdo->prepare(
        "UPDATE pagos_prestamos SET estado = 'pagado', fecha_pago = :fecha WHERE id = :pago_id AND fk_solicitud = :prestamo_id"
);
$fecha = date('Y-m-d H:i:s');
$marcarPago->bindParam(':fecha', $fecha);
$marcarPago->bindParam(':pago_id', $pago_id);
$marcarPago->bindParam(':prestamo_id', $prestamo_id);
$marcarPago->execute();

if ($marcarPago->rowCount() > 0) {
    $r = http_build_query([
            'id' => $prestamo_id,
            'message' => 'Pago registrado correctamente.'
    ]);
} else {
    $r = http_build_query([
            'id' => $prestamo_id,
            'message' => 'No se pudo registrar el pago.',
            'status' => 'error'
    ]);
}
header("Location: ver.php?$r");
exit;