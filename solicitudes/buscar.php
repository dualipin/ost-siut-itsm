<?php
require_once __DIR__ . '/../src/configuracion.php';

header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';
$q = trim($q);

if ($q === '') {
    echo json_encode([]);
    exit;
}

$con = App\Configuracion\MysqlConexion::conexion();

$stmt = $con->prepare("
    SELECT 
        id, 
        CONCAT(nombre, ' ', apellidos) AS name
    FROM miembros
    WHERE CONCAT(nombre, ' ', apellidos) LIKE ?
    LIMIT 10
");
$stmt->execute(["%$q%"]);

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result);
