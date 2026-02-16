<?php
use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;

require_once __DIR__ . '/../../src/configuracion.php';
SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $con = MysqlConexion::conexion();
    $stmt = $con->prepare('DELETE FROM formatos WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: index.php?mensaje=Formato eliminado');
    exit();
}
header('Location: index.php?error=No se pudo eliminar');
