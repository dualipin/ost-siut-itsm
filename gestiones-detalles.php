<?php

use App\Configuracion\MysqlConexion;
use App\Fabricas\FabricaLatte;

require_once __DIR__ . '/src/configuracion.php';


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /avisos.php');
    exit;
}

$con = MysqlConexion::conexion();
$stmt = $con->prepare('SELECT * FROM publicaciones WHERE id = ?');
$stmt->execute([$_GET['id']]);
$aviso = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aviso = $aviso ? $aviso[0] : null;


if (!$aviso) {
    header('Location: /avisos.php');
    exit;
}

$datos = [
        'publicacion' => $aviso
];

FabricaLatte::obtenerInstancia()
        ->render(__DIR__ . '/avisos-detalles.latte', $datos);