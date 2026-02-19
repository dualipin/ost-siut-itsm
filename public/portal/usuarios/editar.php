<?php


use App\Configuracion\MysqlConexion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';


$id = (int)($_GET['id'] ?? 0);
$pdo  = MysqlConexion::conexion();

// 1. Agremiado
$stmt = $pdo->prepare('SELECT * FROM miembros WHERE id = ?');
$stmt->execute([$id]);
$agremiado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agremiado) {
    // Redirige o muestra 404
    header('Location: ./index.php?error=no_encontrado');
    exit;
}

// 2. Usuario (ahora seguro)
$stmt = $pdo->prepare('SELECT correo FROM usuarios WHERE id = ?');
$stmt->execute([$agremiado['fk_usuario']]);
$usuarioRow = $stmt->fetch(PDO::FETCH_ASSOC);
$usuario = $usuarioRow ?: ['correo' => '']; // <-- clave siempre definida

// 3. Documentos (mismo truco de claves)
$stmt = $pdo->prepare('SELECT * FROM documentos_agremiados WHERE miembro_id = ?');
$stmt->execute([$id]);
$docsRow = $stmt->fetch(PDO::FETCH_ASSOC);
$keys = ['perfil','afiliacion','comprobante_domicilio','ine','comprobante_pago'];
$docs = array_fill_keys($keys, '');
if ($docsRow) $docs = array_merge($docs, $docsRow);

ServicioLatte::renderizar(__DIR__ . '/editar.latte', [
        'agremiado' => $agremiado,
        'usuario' => $usuario,
        'docs' => $docs
]);

//
//use App\Configuracion\MysqlConexion;
//use App\Manejadores\SesionProtegida;
//use App\Servicios\ServicioLatte;
//
//SesionProtegida::proteger();
//SesionProtegida::rolesAutorizados(['administrador', 'lider']);
//
//if (!isset($_GET['id'])) {
//    header('Location: index.php');
//    exit;
//}
//
//$con = MysqlConexion::conexion();
//
//$stmt = $con->prepare("SELECT *
//    FROM miembros
//    INNER JOIN usuarios ON miembros.fk_usuario = usuarios.id
//    WHERE miembros.id = :id");
//
//$stmt->execute(['id' => $_GET['id']]);
//$agremiado = $stmt->fetch(PDO::FETCH_ASSOC);
//
//if (!$agremiado) {
//    header('Location: index.php');
//    exit;
//}
//
//$datos = [
//        'agremiado' => $agremiado,
//];
//
//ServicioLatte::renderizar(__DIR__ . '/editar.latte', $datos);