<?php

use App\Configuracion\MysqlConexion;

require_once __DIR__ . '/../src/configuracion.php';

$error = $mensaje = null;

if ($_SERVER["REQUEST_METHOD"] !== "POST") header("Location: index.php");

$miembroId = filter_var($_POST['miembro_id'], FILTER_VALIDATE_INT);
if ($miembroId === false) {
    $error = 'ID de miembro inválido.';
    header('Location: index.php?error=' . urlencode($error));
    exit;
}

$con = MysqlConexion::conexion();

$res = $con->prepare("SELECT id FROM miembros WHERE id = :id");;
$res->execute([':id' => $miembroId]);
$miembro = $res->fetch(PDO::FETCH_ASSOC);
if (!$miembro) {
    $error = 'Miembro no encontrado, o no pertenece al sistema.';
    header('Location: index.php?error=' . urlencode($error));
    exit;
}

// Verificar si el miembro ya envió una propuesta este año
$anioActual = date('Y');
$res = $con->prepare('SELECT id FROM propuestas WHERE miembro_id = :miembro_id AND YEAR(fecha) = :anio');
$res->execute([':miembro_id' => $miembroId, ':anio' => $anioActual]);
$propuestaExistente = $res->fetch(PDO::FETCH_ASSOC);
if ($propuestaExistente) {
    $error = 'Ya has enviado una propuesta este año.';
    header('Location: index.php?error=' . urlencode($error));
    exit;
}

$res = $con->prepare('SELECT id FROM propuestas WHERE miembro_id = :miembro_id AND fecha ');

$sqlPropuesta = "INSERT INTO propuestas (miembro_id) VALUES (:miembro_id)";
$sqlPropuestaContenido = "INSERT INTO propuestas_contenido (propuesta_id, contenido, tipo)
VALUES (:propuesta_id, :contenido, :tipo)";


try {
    $con->beginTransaction();
    $stmtPropuesta = $con->prepare($sqlPropuesta);
    $stmtPropuestaContenido = $con->prepare($sqlPropuestaContenido);

    // Insertar propuesta
    $stmtPropuesta->execute([
            ':miembro_id' => $_POST['miembro_id'],
    ]);

    $propuestaId = (int)$con->lastInsertId();

    // Insertar contenidos de la propuesta
    for ($i = 1; $i <= 4; $i++) {
        $contenido = $_POST["peticion-$i"] ?? '';
        $tipo = $_POST["tipo-$i"] ?? '';
        if ($contenido) {
            $stmt = $con->prepare("
                INSERT INTO propuestas_contenido (propuesta_id, contenido, tipo)
                VALUES (:propuesta_id, :contenido, :tipo)
            ");
            $stmt->execute([
                    ':propuesta_id' => $propuestaId,
                    ':contenido' => $contenido,
                    ':tipo' => $tipo
            ]);
        }
    }

    $comentarios = $_POST['comentarios'] ?? '';
    if ($comentarios) {
        $stmt = $con->prepare("
            INSERT INTO propuestas_contenido (propuesta_id, contenido, tipo)
            VALUES (:propuesta_id, :contenido, 'comentario')
        ");
        $stmt->execute([
                ':propuesta_id' => $propuestaId,
                ':contenido' => $comentarios
        ]);
    }

    $con->commit();
    $mensaje = 'Propuesta registrada exitosamente.';
} catch (Exception $e) {
    $con->rollBack();
    $error = 'Error al registrar la propuesta: ' . $e->getMessage();
}

if ($error) {
    header('Location: index.php?error=' . urlencode($error));
    exit;
}

header('Location: index.php?mensaje=' . urlencode($mensaje));;
exit;
