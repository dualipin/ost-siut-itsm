<?php

use App\Configuracion\MysqlConexion;

require_once __DIR__ . '/../../src/configuracion.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ./index.php?error=sin_id');
    exit;
}


$pdo = MysqlConexion::conexion();
$stmt = $pdo->prepare('UPDATE miembros SET activo = false WHERE id = ?');
$stmt->execute([(int)$id]);
header('Location: ./index.php?msg=eliminado');
exit;