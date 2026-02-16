<?php

require_once __DIR__ . '/../../src/configuracion.php';

$pdo = \App\Configuracion\MysqlConexion::conexion();
header('Content-Type: application/json');
$noticias = $pdo->query(
        "SELECT id,titulo,resumen,fecha FROM publicaciones WHERE tipo='noticia' ORDER BY fecha DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($noticias);