<?php
use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';
SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$con = MysqlConexion::conexion();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mensaje = $error = null;

if ($id > 0) {
    $stmt = $con->prepare('SELECT * FROM formatos WHERE id = ?');
    $stmt->execute([$id]);
    $formato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$formato) {
        header('Location: index.php?error=No se encontró el formato');
        exit();
    }
} else {
    header('Location: index.php?error=ID inválido');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $descripcion = filter_input(INPUT_POST, 'descripcion');
    $nombreArchivo = $formato['archivo'];

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $directorio = __DIR__ . '/../../archivos/subidos/formatos/';
        $nombreOriginal = basename($_FILES['archivo']['name']);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $tamanoMax = 5 * 1024 * 1024;
        if (in_array($extension, $permitidos, true) && (int)$_FILES['archivo']['size'] <= $tamanoMax) {
            $nombreArchivo = uniqid('form_', true) . '.' . $extension;
            $rutaDestino = $directorio . $nombreArchivo;
            move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino);
        }
    }

    $sql = "UPDATE formatos SET titulo = :titulo, descripcion = :descripcion, archivo = :archivo WHERE id = :id";
    $stmt = $con->prepare($sql);
    $stmt->bindValue(':titulo', $titulo);
    $stmt->bindValue(':descripcion', $descripcion);
    $stmt->bindValue(':archivo', $nombreArchivo);
    $stmt->bindValue(':id', $id);
    if ($stmt->execute()) {
        $mensaje = "Formato actualizado con éxito.";
        header('Location: index.php?mensaje=' . urlencode($mensaje));
        exit();
    } else {
        $error = "Error al actualizar el formato.";
    }
}
ServicioLatte::renderizar(__DIR__ . '/gestionar.latte', array_merge($formato, ['mensaje' => $mensaje, 'error' => $error]));
