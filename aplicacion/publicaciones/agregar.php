<?php


use App\Configuracion\MysqlConexion;
use App\Manejadores\Sesion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../src/configuracion.php';

SesionProtegida::proteger();
SesionProtegida::rolesAutorizados(['administrador', 'lider']);

$mensaje = $error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $resumen = filter_input(INPUT_POST, 'resumen', FILTER_SANITIZE_SPECIAL_CHARS);
    $contenido = filter_input(INPUT_POST, 'contenido');
    $importante = filter_input(INPUT_POST, 'importante', FILTER_VALIDATE_BOOLEAN);
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS);
    $fechaLimite = filter_input(INPUT_POST, 'fecha_limite');
    $nombreArchivo = null;

    $con = MysqlConexion::conexion();

    $sql = "INSERT INTO publicaciones (titulo, resumen, contenido, imagen, expiracion, fk_miembro, tipo) VALUES (
        :titulo,
        :resumen,
        :contenido,
        :imagen,
        :expiracion,
        :fk_miembro,
        :tipo
    )";

    // Manejo opcional de archivo
    if (!$error && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $directorio = __DIR__ . '/../../archivos/subidos/publicaciones/';
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
            $error = 'No se pudo crear el directorio de subida.';
        } else {
            $nombreOriginal = basename($_FILES['archivo']['name']);
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png'];
            $tamanoMax = 2 * 1024 * 1024; // 2MB

            if (!in_array($extension, $permitidos, true)) {
                $error = 'Tipo de archivo no permitido.';
            } elseif ((int)$_FILES['archivo']['size'] > $tamanoMax) {
                $error = 'El archivo excede el tamaño máximo permitido (5MB).';
            } else {
                // Generar nombre único y seguro
                $nombreArchivo = uniqid('img_', true) . '.' . $extension;
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
    $stmt->bindValue(':resumen', $resumen);
    $stmt->bindValue(':contenido', $contenido);
    $stmt->bindValue(':imagen', $nombreArchivo);
    $stmt->bindValue(':expiracion', $fechaLimite ?: null);
    $stmt->bindValue(':fk_miembro', Sesion::idSesionAbierta());
    $stmt->bindValue(':tipo', $tipo);

    if ($stmt->execute()) {
        $mensaje = "Publicación registrado con éxito.";
    } else {
        if ($nombreArchivo) {
            $rutaArchivo = __DIR__ . '/../../archivos/subidos/publicaciones/' . $nombreArchivo;
            if (file_exists($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
        }
        $error = "Error al registrar el aviso.";
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
        "error" => $error,
];

ServicioLatte::renderizar(__DIR__ . '/agregar.latte', $datos);