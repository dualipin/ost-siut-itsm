<?php

use App\Configuracion\MysqlConexion;
use App\Manejadores\SesionProtegida;

require_once __DIR__ . '/../../../src/configuracion.php';

SesionProtegida::proteger();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: index.php');
    exit('Método no permitido');
}

// Sanitización y validación básica
$titulo = trim((string)($_POST['titulo'] ?? ''));
$contenido = trim((string)($_POST['contenido'] ?? ''));
$fecha_documento = trim((string)($_POST['fecha_documento'] ?? ''));
$privado = isset($_POST['privado']) ? 1 : 0;

$nombreArchivo = null;
$error = null;

if ($titulo === '' || $contenido === '') {
    $error = 'El título y el contenido son obligatorios.';
}

// Validar y convertir fecha_documento a formato YYYY-MM-DD o null
if ($fecha_documento !== '') {
    $fechaObj = date_create_from_format('Y-m-d', $fecha_documento);
    if (!$fechaObj) {
        $error = 'La fecha del documento no es válida.';
    } else {
        $fecha_documento = $fechaObj->format('Y-m-d');
    }
} else {
    $fecha_documento = null;
}

// Manejo opcional de archivo
if (!$error && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
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

$conn = MysqlConexion::conexion();

$sql = "INSERT INTO documentos_minutas (titulo, contenido, adjunto, fecha_documento, privado) VALUES (?, ?, ?, ?, ?)";
$params = [
        $titulo,
        $contenido,
        $nombreArchivo,
        $fecha_documento,
        $privado
];

$stmt = $conn->prepare($sql);
if ($stmt->execute($params)) {
    header('Location: index.php?mensaje=' . urlencode('Documento registrado con éxito'));
    exit();
} else {
    // Si la inserción falla y subimos archivo, eliminarlo para no dejar basura
    if ($nombreArchivo) {
        $rutaArchivo = __DIR__ . '/../../privado/archivos/repositorios/minutas-y-acuerdos/' . $nombreArchivo;
        if (file_exists($rutaArchivo)) {
            @unlink($rutaArchivo);
        }
    }
    http_response_code(500);
    header('Location: index.php?error=' . urlencode('Error al registrar el documento'));
    exit('Error al registrar el documento');
}