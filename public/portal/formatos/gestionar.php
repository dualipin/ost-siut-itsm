<?php
use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$mensaje = $error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $descripcion = filter_input(INPUT_POST, 'descripcion');
    $nombreArchivo = null;

    $con = MysqlConexion::conexion();

    $sql = "INSERT INTO formatos (titulo, descripcion, archivo) VALUES (
        :titulo,
        :descripcion,
        :archivo
    )";

    if (!$error && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $directorio = __DIR__ . '/../../archivos/subidos/formatos/';
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
            $error = 'No se pudo crear el directorio de subida.';
        } else {
            $nombreOriginal = basename($_FILES['archivo']['name']);
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $tamanoMax = 5 * 1024 * 1024;
            if (!in_array($extension, $permitidos, true)) {
                $error = 'Tipo de archivo no permitido.';
            } elseif ((int)$_FILES['archivo']['size'] > $tamanoMax) {
                $error = 'El archivo excede el tamaño máximo permitido (5MB).';
            } else {
                $nombreArchivo = uniqid('form_', true) . '.' . $extension;
                $rutaDestino = $directorio . $nombreArchivo;
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
                    $error = 'Error al guardar el archivo.';
                    $nombreArchivo = null;
                }
            }
        }
    }

    if ($error !== null) {
        header('Location: index.php?error=' . urlencode($error));
        exit();
    }

    $stmt = $con->prepare($sql);
    $stmt->bindValue(':titulo', $titulo);
    $stmt->bindValue(':descripcion', $descripcion);
    $stmt->bindValue(':archivo', $nombreArchivo);

    if ($stmt->execute()) {
        $mensaje = "Formato registrado con éxito.";
    } else {
        if ($nombreArchivo) {
            $rutaArchivo = __DIR__ . '/../../archivos/subidos/formatos/' . $nombreArchivo;
            if (file_exists($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
        }
        $error = "Error al registrar el formato.";
    }
    header('Location: ?' . ($error ? 'error=' . urlencode($error) : 'mensaje=' . urlencode($mensaje)));
    exit();
}

if (isset($_GET['mensaje'])) {
    $mensaje = htmlspecialchars($_GET['mensaje']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
$datos = [
    'mensaje' => $mensaje,
    'error' => $error,
];
ServicioLatte::renderizar(__DIR__ . '/gestionar.latte', $datos);
