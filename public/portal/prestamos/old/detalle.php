<?php

require_once __DIR__ . '/../../src/configuracion.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}


$pdo = \App\Configuracion\MysqlConexion::conexion();
$prestamo = $pdo->prepare("SELECT * FROM solicitudes_prestamos WHERE id = :id");
$prestamo->bindParam(':id', $id);
$prestamo->execute();
$prestamo = $prestamo->fetch(PDO::FETCH_ASSOC);

$pagos = $pdo->prepare("SELECT * FROM pagos_prestamos WHERE fk_solicitud = :solicitud_id ORDER BY fecha_pago");
$pagos->bindParam(':solicitud_id', $prestamo['id']);
$pagos->execute();
$prestamo['pagos'] = $pagos->fetchAll(PDO::FETCH_ASSOC);

$datos = [
        'prestamo' => $prestamo,
        'message' => $_GET['message'] ?? null,
        'status' => $_GET['status'] ?? null,
];
\App\Servicios\ServicioLatte::renderizar(
        __DIR__ . '/detalle.latte',
        $datos
);