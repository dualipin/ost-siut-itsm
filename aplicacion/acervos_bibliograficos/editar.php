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
    $stmt = $con->prepare('SELECT * FROM acervos_bibliograficos WHERE id = ?');
    $stmt->execute([$id]);
    $acervo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$acervo) {
        header('Location: index.php?error=No se encontró el acervo');
        exit();
    }
} else {
    header('Location: index.php?error=ID inválido');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $autor = filter_input(INPUT_POST, 'autor', FILTER_SANITIZE_SPECIAL_CHARS);
    $descripcion = filter_input(INPUT_POST, 'descripcion');
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS);
    $nombreArchivo = $acervo['archivo'];

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $directorio = __DIR__ . '/../../archivos/subidos/acervos_bibliograficos/';
        $nombreOriginal = basename($_FILES['archivo']['name']);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $permitidos = ['pdf', 'epub', 'doc', 'docx', 'jpg', 'png'];
        $tamanoMax = 10 * 1024 * 1024;
        if (in_array($extension, $permitidos, true) && (int)$_FILES['archivo']['size'] <= $tamanoMax) {
            $nombreArchivo = uniqid('acervo_', true) . '.' . $extension;
            $rutaDestino = $directorio . $nombreArchivo;
            move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino);
        }
    }

    $sql = "UPDATE acervos_bibliograficos SET titulo = :titulo, autor = :autor, descripcion = :descripcion, tipo = :tipo, archivo = :archivo WHERE id = :id";
    $stmt = $con->prepare($sql);
    $stmt->bindValue(':titulo', $titulo);
    $stmt->bindValue(':autor', $autor);
    $stmt->bindValue(':descripcion', $descripcion);
    $stmt->bindValue(':tipo', $tipo);
    $stmt->bindValue(':archivo', $nombreArchivo);
    $stmt->bindValue(':id', $id);
    if ($stmt->execute()) {
        $mensaje = "Acervo bibliográfico actualizado con éxito.";
        header('Location: index.php?mensaje=' . urlencode($mensaje));
        exit();
    } else {
        $error = "Error al actualizar el acervo bibliográfico.";
    }
}
ServicioLatte::renderizar(__DIR__ . '/gestionar.latte', array_merge($acervo, ['mensaje' => $mensaje, 'error' => $error]));
