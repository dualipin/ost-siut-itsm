<?php

declare(strict_types=1);

use App\Manejadores\Sesion;
use App\Servicios\ServicioLatte;
use App\Configuracion\MysqlConexion;

require_once __DIR__ . '/../src/configuracion.php';

\App\Manejadores\SesionProtegida::proteger();
$pdo = MysqlConexion::conexion();
$user = Sesion::sesionAbierta();
$idMiembro = $user->getId();

/* Guardar mensaje */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensaje'])) {
    $mensaje = trim($_POST['mensaje']);
    if ($mensaje !== '') {
        $ins = $pdo->prepare("INSERT INTO buzon (miembro_id, mensaje) VALUES (?, ?)");
        $ins->execute([$idMiembro, $mensaje]);
        header("Location: buzon.php");
        exit;
    }
}

/* Guardar respuesta (solo admin/lider) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respuesta'], $_POST['buzon_id']) && ($user->esAdmin(
                ) || $user->esLider())) {
    $respuesta = trim($_POST['respuesta']);
    $buzonId = (int)$_POST['buzon_id'];
    if ($respuesta !== '') {
        $ins = $pdo->prepare("INSERT INTO buzon_respuestas (buzon_id, respuesta) VALUES (?, ?)");
        $ins->execute([$buzonId, $respuesta]);
        header("Location: buzon.php");
        exit;
    }
}

/* Listado */
if ($user->esAdmin() || $user->esLider()) {
    $mensajes = $pdo->query(
            "SELECT b.id, b.mensaje, b.fecha, m.nombre, m.apellidos
                             FROM buzon b
                             JOIN miembros m ON m.id = b.miembro_id
                             ORDER BY b.fecha DESC"
    )->fetchAll();
} else {
    $mensajes = $pdo->prepare(
            "SELECT b.id, b.mensaje, b.fecha
                               FROM buzon b
                               WHERE b.miembro_id = ?
                               ORDER BY b.fecha DESC"
    );
    $mensajes->execute([$idMiembro]);
    $mensajes = $mensajes->fetchAll();
}

/* Respuestas por mensaje */
$respuestas = [];
foreach ($mensajes as $m) {
    $res = $pdo->prepare("SELECT respuesta, fecha FROM buzon_respuestas WHERE buzon_id = ? ORDER BY fecha");
    $res->execute([$m['id']]);
    $respuestas[$m['id']] = $res->fetchAll();
}

ServicioLatte::renderizar(__DIR__ . '/buzon.latte', [
        'rol' => $user->getRol(),
        'mensajes' => $mensajes,
        'respuestas' => $respuestas,
]);
