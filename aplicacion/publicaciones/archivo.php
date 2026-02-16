<?php

$baseDir = __DIR__ . '/../../archivos/subidos/publicaciones/';

// Crear carpeta si no existe
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

$filename = 'img_' . uniqid() . '.' . pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
$destino = $baseDir . $filename;

if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
    echo json_encode(['error' => 'Failed to move file']);
    exit;
}

// URL pública (AJÚSTALA A TU PROYECTO REAL)
$urlPublica = '/archivos/subidos/publicaciones/' . $filename;

echo json_encode(['url' => $urlPublica]);
