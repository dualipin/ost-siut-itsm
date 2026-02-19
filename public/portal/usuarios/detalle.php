<?php

use App\Configuracion\MysqlConexion;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';


$id = (int)($_GET['id'] ?? 0);
$pdo = MysqlConexion::conexion();

$agremiado = $pdo->prepare('SELECT * FROM miembros WHERE id = ?');
$agremiado->execute([$id]);
$agremiado = $agremiado->fetch(PDO::FETCH_ASSOC);

if (!$agremiado) {
    // Redirige o muestra 404
    header('Location: ./index.php?error=no_encontrado');
    exit;
}

$usuario = $pdo->prepare('SELECT correo, rol FROM usuarios WHERE id = ?');
$usuario->execute([$agremiado['fk_usuario']]);
$usuario = $usuario->fetch(PDO::FETCH_ASSOC);
$agremiado['usuario'] = $usuario ?: ['correo' => '', 'rol' => ''];

$documentos = $pdo->prepare('SELECT * FROM documentos_agremiados WHERE miembro_id = ?');
$documentos->execute([$id]);
$documentos = $documentos->fetch(PDO::FETCH_ASSOC);
$keys = ['perfil', 'afiliacion', 'comprobante_domicilio', 'ine', 'comprobante_pago'];
$docs = array_fill_keys($keys, '');
if ($documentos) {
    $docs = array_merge($docs, $documentos);
}
$agremiado['documentos'] = $docs;

$datos = [
    'agremiado' => $agremiado,
];

ServicioLatte::renderizar(__DIR__ . '/detalle.latte', $datos);