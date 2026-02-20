<?php

require_once __DIR__ . '/../../src/configuracion.php';

$id = $_POST['id'] ?? null;
$estado = $_POST['estado'] ?? null;


if ($id === null || $estado === null) {
    $r = http_build_query([
            'id' => $id,
            'message' => 'Parámetros inválidos.',
            'status' => 'error'
    ]);

    header("Location: ver.php?$r");
    exit;
}


$pdo = \App\Configuracion\MysqlConexion::conexion();

$actualizar = $pdo->prepare("UPDATE solicitudes_prestamos SET estado = :estado WHERE id = :id");
$actualizar->bindParam(':estado', $estado);
$actualizar->bindParam(':id', $id);
$actualizar->execute();

if ($actualizar->rowCount() > 0) {
    echo json_encode(['message' => 'Estado actualizado.']);
} else {
    echo json_encode(['message' => 'No se pudo actualizar el estado.', 'status' => 'error']);
}