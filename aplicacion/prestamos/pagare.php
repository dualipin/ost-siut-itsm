<?php

declare(strict_types=1);


use App\Configuracion\MysqlConexion;

require_once __DIR__ . '/../../src/configuracion.php';

$id = $_POST['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}


$pdo = MysqlConexion::conexion();
$prestamo = $pdo->prepare("SELECT id FROM solicitudes_prestamos WHERE id = :id");
$prestamo->bindParam(':id', $id);
$prestamo->execute();
$prestamo = $prestamo->fetch(PDO::FETCH_ASSOC);
if (!$prestamo) {
    header("Location: index.php");
    exit;
}


$file = $_FILES['pagare'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $r = http_build_query(
            [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Error al subir el archivo.'
            ]
    );
    header("Location: detalle.php?$r");
}

$uploadDir = __DIR__ . '/../../archivos/subidos/pagare/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uploadFile = $uploadDir . 'pagare_prestamo_' . $id . '.' . $extension;
if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
    $r = http_build_query(
            [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Error al guardar el archivo.'
            ]
    );
} else {
    $r = http_build_query(
            [
                    'id' => $id,
                    'status' => 'success',
                    'message' => 'Pagaré subido correctamente.'
            ]
    );
}

$update = $pdo->prepare(
        "UPDATE solicitudes_prestamos SET pagare_firmado = :path, estado= 'pagare_pendiente', fecha_pagare = NOW() WHERE id = :id"
)
        ->execute([
                'path' => 'archivos/subidos/pagare/' . 'pagare_prestamo_' . $id . '.' . $extension,
                'id' => $id
        ]);

if (!$update) {
    @unlink($uploadFile);
    $r = http_build_query(
            [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Error al actualizar el registro del pagaré.'
            ]
    );
}

header("Location: detalle.php?$r");
exit;