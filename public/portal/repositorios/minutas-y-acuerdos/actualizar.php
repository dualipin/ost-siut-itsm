<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;
use App\Servicios\ServicioLatte;

require_once __DIR__ . '/../../../src/configuracion.php';
require_once __DIR__ . '/buscar.php';

SesionProtegida::proteger();
$conn = MysqlConexion::conexion();
$error = null;
$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id_doc'];
    $titulo = $_POST['titulo'] ?? '';
    $contenido = $_POST['contenido'] ?? '';
    $fecha_documento = $_POST['fecha_documento'] ?? null;
    $privado = isset($_POST['privado']) ? 1 : 0;

    // Obtener el adjunto actual si no se sube uno nuevo
    $adjuntoActual = null;
    $stmtAdjunto = $conn->prepare('SELECT adjunto FROM documentos_minutas WHERE id = ?');
    $stmtAdjunto->execute([$id]);
    $filaAdjunto = $stmtAdjunto->fetch(PDO::FETCH_ASSOC);
    if ($filaAdjunto) {
        $adjuntoActual = $filaAdjunto['adjunto'];
    }

    $adjunto = $adjuntoActual;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $directorio = __DIR__ . '/../../../privado/archivos/repositorios/minutas-y-acuerdos/';
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
            $error = 'No se pudo crear el directorio de subida.';
        } else {
            $nombreOriginal = basename($_FILES['archivo']['name']);
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            $permitidos = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $tamanoMax = 5 * 1024 * 1024; // 5MB
            if (!in_array($extension, $permitidos, true)) {
                $error = 'Tipo de archivo no permitido.';
            } elseif ((int)$_FILES['archivo']['size'] > $tamanoMax) {
                $error = 'El archivo excede el tamaño máximo permitido (5MB).';
            } else {
                // Generar nombre único y seguro
                $nombreArchivo = uniqid('doc_', true) . '.' . $extension;
                $rutaDestino = $directorio . $nombreArchivo;
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
                    $error = 'Error al subir el archivo.';
                } else {
                    $adjunto = $nombreArchivo;
                }
            }
        }
    }

    $sql = "UPDATE `documentos_minutas` SET `titulo`= ?,`contenido`= ?,`adjunto`= ?,`fecha_documento`= ?,`privado`= ? WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $titulo);
    $stmt->bindParam(2, $contenido);
    $stmt->bindParam(3, $adjunto);
    $stmt->bindParam(4, $fecha_documento);
    $stmt->bindParam(5, $privado, PDO::PARAM_INT);
    $stmt->bindParam(6, $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        header('Location: detalle.php?id_doc=' . $id . '&mensaje=' . urlencode('Documento actualizado con éxito.'));
        exit('Documento actualizado con éxito.');
    } else {
        // Si la actualización falla y subimos archivo, eliminarlo para no dejar basura
        if ($adjunto !== $adjuntoActual && $adjunto) {
            $rutaArchivo = __DIR__ . '/../../../privado/archivos/repositorios/minutas-y-acuerdos/' . $adjunto;
            if (file_exists($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
        }
    }

    header('Location: ?id_doc=' . $id . '&error=' . urlencode('Error al actualizar el documento.'));
    exit('Funcionalidad de actualización en desarrollo.');
}

// Validar ID de documento
if (!isset($_GET['id_doc']) || !is_numeric($_GET['id_doc'])) {
    http_response_code(400);
    header('Location: index.php?error=' . urlencode('ID de documento inválido.'));
    exit('ID de documento inválido.');
}


if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}

$documento = buscarDocumento($conn, (int)$_GET['id_doc']);

if (!$documento) {
    http_response_code(404);
    $error = 'Documento no encontrado.';
    header('Location: index.php?error=' . urlencode($error));
    exit('Documento no encontrado.');
}

$datos = [
        'documento' => $documento,
        'error' => $error,
        'mensaje' => $mensaje,
];

ServicioLatte::renderizar(__DIR__ . '/actualizar.latte', $datos);

